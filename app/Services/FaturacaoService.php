<?php

namespace App\Services;

use App\Models\Corporate;
use App\Models\CorporateFatura;
use App\Models\PreparacaoItem;
use App\Models\WooOrder;
use App\Models\WooProduct;
use App\Support\CabazProdutoResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Orquestra a emissao de documentos Moloni:
 *  - B2C: Fatura-Recibo ao terminar a subscricao, com o cabaz composto
 *         (child_products) a partir dos produtos registados na preparacao.
 *  - B2B: Fatura por empresa, agrupando as sucursais com o mesmo contribuinte
 *         num unico documento com um cabaz composto por sucursal.
 */
class FaturacaoService
{
    public function __construct(
        private readonly MoloniService $moloni,
        private readonly CabazProdutoResolver $resolver,
        private readonly CompostoCabazService $composto,
    ) {}

    // ==================================================================
    //  B2C — Subscricoes (Fatura-Recibo)
    // ==================================================================

    /**
     * Emite a Fatura-Recibo de uma subscricao ja paga no site.
     *
     * @return array{document_id:int,pdf_url:?string}
     */
    public function emitirFaturaReciboSubscricao(WooOrder $order, bool $forcar = false, ?int $metodoPagamento = null): array
    {
        if ($order->fatura_document_id && ! $forcar) {
            throw new RuntimeException('Esta subscricao ja tem uma fatura emitida (#'.$order->fatura_document_id.').');
        }

        $documentSetId = $this->moloni->documentSetId('fatura_recibo');

        if ($documentSetId === null) {
            throw new RuntimeException('Falta configurar MOLONI_DOCUMENT_SET_ID_FATURA_RECIBO no .env.');
        }

        $taxId = $this->moloni->taxId();
        $taxValue = (float) config('moloni.default_tax_value', 6);

        $totalPago = round((float) $order->total, 2);
        $periodo = ($order->subscription_ends_at ?? now())->format('Y-m');
        $composicao = $this->composicaoSubscricao($order, $periodo, $taxId, $taxValue);

        // Total do cabaz = soma dos sub-produtos (cada um com o seu preco). Se ainda
        // nao houver precos por produto, usa o preco da subscricao no site.
        $precoLiquido = $composicao['total_liquido'] > 0
            ? $composicao['total_liquido']
            : $this->precoLiquido($totalPago, $taxValue);

        // A Fatura-Recibo tem de balancear: o pagamento iguala o total do documento.
        $totalDocumento = round($precoLiquido * (1 + ($taxValue > 0 ? $taxValue / 100 : 0)), 2);

        $linha = $this->linhaCabaz(
            nome: $this->nomeCabazSubscricao($order),
            precoLiquido: $precoLiquido,
            taxId: $taxId,
            taxValue: $taxValue,
            childProducts: $composicao['moloni'],
        );

        $payload = [
            'date' => now()->toDateString(),
            'expiration_date' => now()->toDateString(),
            'document_set_id' => $documentSetId,
            'customer_id' => $this->moloni->obterOuCriarCliente($this->clienteDeWooOrder($order)),
            'status' => config('moloni.fechar_documentos') ? 1 : 0,
            'products' => [$linha],
            'payments' => $this->pagamentosFaturaRecibo($totalDocumento, $metodoPagamento),
            'notes' => 'Subscricao Horta da Maria #'.$order->woo_id,
        ];

        $resultado = $this->moloni->inserirFaturaRecibo($payload);

        $order->forceFill([
            'fatura_document_id' => $resultado['document_id'],
            'fatura_tipo' => 'fatura_recibo',
            'fatura_emitida_em' => now(),
            'cabaz_itens_faturados' => $composicao['resumo'],
        ])->save();

        return [
            'document_id' => $resultado['document_id'],
            'pdf_url' => $this->moloni->pdfUrl($resultado['document_id']),
        ];
    }

    /**
     * Constroi o cabaz composto a partir dos produtos registados na preparacao
     * (produtos_picados) de todas as entregas da subscricao, agregando e
     * resolvendo os nomes (fruta da epoca, kiwi, etc.).
     *
     * @return array{moloni:array<int,array>,resumo:array<int,array>}
     */
    private function composicaoSubscricao(WooOrder $order, string $periodo, ?int $taxId, float $taxValue): array
    {
        // 1) Produtos colocados manualmente para a fatura (na pagina da encomenda).
        $picados = collect($order->fatura_produtos ?? [])->filter();

        // 2) Senao, o que foi registado como picado na preparacao.
        if ($picados->isEmpty()) {
            $picados = PreparacaoItem::query()
                ->where('tipo', 'b2c')
                ->where('woo_order_id', $order->id)
                ->get()
                ->flatMap(fn (PreparacaoItem $item): array => (array) ($item->produtos_picados ?? []));
        }

        // 3) Senao, cai para os artigos da encomenda.
        if ($picados->isEmpty()) {
            $picados = collect($order->line_items ?? [])
                ->map(fn ($item) => is_array($item) ? ($item['name'] ?? null) : $item)
                ->filter();
        }

        return $this->agregarComposicao($picados, $periodo, $taxId, $taxValue);
    }

    // ==================================================================
    //  B2B — Empresas (UMA FATURA POR SUCURSAL)
    // ==================================================================

    /**
     * Emite as Faturas de todas as sucursais ativas com este contribuinte —
     * UMA FATURA POR SUCURSAL, nunca agrupadas (decisao do André, 28/08/2026).
     * O cliente Moloni e o mesmo (o NIF), mudam so os documentos.
     *
     * @return array{documentos:array<int,array>,document_ids:array<int,int>,corporate_ids:array<int,int>,erros:array<int,string>}
     */
    public function emitirFaturaEmpresasPorNif(string $nif, \Illuminate\Support\Carbon $dataRef, bool $forcar = false, ?string $referenciaCliente = null): array
    {
        $nif = trim($nif);

        $empresas = Corporate::query()
            ->where('ativo', true)
            ->where('fatura_nif', $nif)
            ->orderBy('empresa')
            ->orderBy('sucursal')
            ->get();

        if ($empresas->isEmpty()) {
            throw new RuntimeException("Nao ha empresas ativas com o contribuinte {$nif}.");
        }

        // NUNCA agrupar sucursais: uma fatura por sucursal (decisao do André,
        // 28/08/2026). Um erro numa sucursal nao impede as restantes.
        $documentos = [];
        $erros = [];

        foreach ($empresas as $empresa) {
            try {
                $documentos[] = $this->emitirFaturaSucursal($empresa, $dataRef, $forcar, $referenciaCliente);
            } catch (\Throwable $exception) {
                $erros[] = trim($empresa->empresa.' '.($empresa->sucursal ?? '')).': '.$exception->getMessage();
            }
        }

        if ($documentos === []) {
            throw new RuntimeException('Nenhuma fatura emitida. '.implode(' | ', $erros));
        }

        return [
            'documentos' => $documentos,
            'document_ids' => array_column($documentos, 'document_id'),
            'corporate_ids' => array_merge(...array_column($documentos, 'corporate_ids')),
            'erros' => $erros,
        ];
    }

    /**
     * Emite a Fatura de uma unica empresa/sucursal.
     *
     * @return array{document_id:int,pdf_url:?string,corporate_ids:array<int,int>}
     */
    public function emitirFaturaEmpresa(Corporate $empresa, \Illuminate\Support\Carbon $dataRef, bool $forcar = false, ?string $referenciaCliente = null): array
    {
        return $this->emitirFaturaSucursal($empresa, $dataRef, $forcar, $referenciaCliente);
    }

    /**
     * @return array{document_id:int,pdf_url:?string,corporate_ids:array<int,int>}
     */
    private function emitirFaturaSucursal(Corporate $empresa, \Illuminate\Support\Carbon $dataRef, bool $forcar, ?string $referenciaCliente): array
    {
        return $this->emitirFaturaParaEmpresas(collect([$empresa]), $empresa, $dataRef, $forcar, $referenciaCliente);
    }

    /**
     * Nucleo de emissao da Fatura para um conjunto de empresas.
     *
     * @param  Collection<int,Corporate>  $empresas
     * @return array{document_id:int,pdf_url:?string,corporate_ids:array<int,int>}
     */
    private function emitirFaturaParaEmpresas(Collection $empresas, Corporate $referencia, \Illuminate\Support\Carbon $dataRef, bool $forcar, ?string $referenciaCliente): array
    {
        $customerId = $this->moloni->obterOuCriarCliente($this->clienteDeCorporate($referencia));

        // Ciclo de faturacao: 4 semanas a contar da ultima fatura do cliente no
        // Moloni (ultima data + 4 semanas). Sem historico, cai para o ciclo da
        // empresa ou para a data de referencia.
        $ultimaFatura = $this->moloni->ultimaFaturaData($customerId);

        if ($ultimaFatura !== null) {
            $cicloInicio = $ultimaFatura->copy()->startOfDay()->addDay();
            $cicloFim = $ultimaFatura->copy()->startOfDay()->addWeeks(4);
        } else {
            $cicloModelo = $referencia->cicloFaturacao($dataRef);

            if ($cicloModelo !== null) {
                $cicloInicio = $cicloModelo['inicio'];
                $cicloFim = $cicloModelo['fim'];
            } else {
                $cicloInicio = $dataRef->copy()->startOfDay();
                $cicloFim = $cicloInicio->copy()->addWeeks(4)->subDay();
            }
        }

        $periodoYm = $cicloInicio->format('Y-m');
        $cicloRef = $cicloInicio->format('Y-m-d');
        $cicloLabel = $cicloInicio->format('d/m').' a '.$cicloFim->format('d/m/Y');

        // V/Ref (A sua referencia) = periodo do ciclo, como nas faturas manuais.
        $periodoTexto = 'Período de fatura de '.$cicloLabel;

        // Deduplicacao POR SUCURSAL (nao por NIF): cada sucursal tem a sua fatura.
        $existente = CorporateFatura::query()
            ->where('ciclo_ref', $cicloRef)
            ->whereJsonContains('corporate_ids', $referencia->id)
            ->first();

        $aindaNoMoloni = null;

        if ($existente && ! $forcar) {
            // Se a fatura foi APAGADA no Moloni, o registo local nao pode
            // bloquear a reemissao — limpa-o e segue.
            $aindaNoMoloni = $this->moloni->documentoExiste((int) $existente->document_id);

            if ($aindaNoMoloni === false) {
                $existente->delete();
                $existente = null;
            }
        }

        if ($existente && ! $forcar) {
            $nomeSucursal = trim($referencia->empresa.' '.($referencia->sucursal ?? ''));
            $aviso = $aindaNoMoloni === null ? ' (nao foi possivel confirmar no Moloni)' : '';
            throw new RuntimeException("Ja existe fatura para {$nomeSucursal} no ciclo {$cicloLabel} (#{$existente->document_id}){$aviso}. Marca 'Forcar' para emitir na mesma.");
        }

        $documentSetId = $this->moloni->documentSetId('fatura');

        if ($documentSetId === null) {
            throw new RuntimeException('Falta configurar MOLONI_DOCUMENT_SET_ID_FATURA no .env.');
        }

        $taxId = $this->moloni->taxId();
        $taxValue = (float) config('moloni.default_tax_value', 6);

        $linhas = [];
        $itensResumo = [];
        $notasEmpresas = [];
        $total = 0.0;
        $ordem = 1;

        foreach ($empresas as $empresa) {
            $linhasEmpresa = [];

            // 1) Cabaz de fruta = artigo composto.
            $linhaEmpresa = $this->linhaCabazEmpresa($empresa, $periodoYm, $taxId, $taxValue);

            if ($linhaEmpresa !== null) {
                $linhasEmpresa[] = $linhaEmpresa['moloni'];
                $itensResumo[] = $linhaEmpresa['resumo'];
                $total += $linhaEmpresa['total_com_iva'];
            }

            // 2) Outros produtos (pastelaria / produtos mensais) = linhas proprias.
            $extras = $this->linhasExtrasEmpresa($empresa, $periodoYm, $taxId, $taxValue);

            foreach ($extras['moloni'] as $linhaExtra) {
                $linhasEmpresa[] = $linhaExtra;
            }

            if ($extras['resumo'] !== []) {
                $itensResumo[] = ['corporate_id' => $empresa->id, 'produtos_extra' => $extras['resumo']];
            }

            $total += $extras['total_com_iva'];

            // 3) Transporte/portes = linha propria, com o IVA da taxa normal.
            $portes = $this->linhaPortesEmpresa($empresa);

            if ($portes !== null) {
                $linhasEmpresa[] = $portes['moloni'];
                $itensResumo[] = $portes['resumo'];
                $total += $portes['total_com_iva'];
            }

            if ($linhasEmpresa === []) {
                continue;
            }

            foreach ($linhasEmpresa as $linhaMoloni) {
                $linhaMoloni['order'] = $ordem++;
                $linhas[] = $linhaMoloni;
            }

            $notasEmpresas[] = $this->observacoesEmpresa($empresa, $cicloInicio, $cicloFim);
        }

        if ($linhas === []) {
            throw new RuntimeException("Nenhuma empresa tem cabaz/preco definido para faturar (ciclo {$cicloLabel}).");
        }

        $dias = (int) ($referencia->dias_vencimento ?: config('moloni.fatura_dias_vencimento', 30));

        $payload = [
            'date' => now()->toDateString(),
            'expiration_date' => now()->copy()->addDays($dias)->toDateString(),
            'document_set_id' => $documentSetId,
            'customer_id' => $customerId,
            'status' => config('moloni.fechar_documentos') ? 1 : 0,
            'products' => $linhas,
            // V/Ref.a = referencia do CLIENTE (a OC deles); Enc./Orc. = periodo do ciclo.
            'your_reference' => (string) ($referenciaCliente ?: $referencia->referencia_cliente ?: ''),
            'our_reference' => $periodoTexto,
            'notes' => implode("\n\n", array_filter($notasEmpresas)),
        ];

        $resultado = $this->moloni->inserirFatura($payload);

        CorporateFatura::create([
            'nif' => $referencia->fatura_nif ?: null,
            'nome' => trim(($referencia->fatura_nome ?: $referencia->empresa).' '.($referencia->sucursal ?? '')),
            'periodo' => $periodoYm,
            'ciclo_ref' => $cicloRef,
            'ciclo_label' => $cicloLabel,
            'referencia_cliente' => $referenciaCliente ?: ($referencia->referencia_cliente ?: null),
            'document_id' => $resultado['document_id'],
            'tipo' => 'fatura',
            'total' => round($total, 2),
            'corporate_ids' => $empresas->pluck('id')->all(),
            'itens' => $itensResumo,
            'emitida_em' => now(),
        ]);

        return [
            'document_id' => $resultado['document_id'],
            'pdf_url' => $this->moloni->pdfUrl($resultado['document_id']),
            'corporate_ids' => $empresas->pluck('id')->all(),
        ];
    }

    /**
     * Linha de cabaz composto de uma sucursal (empresa).
     *
     * @return array{moloni:array,resumo:array,total_com_iva:float}|null
     */
    private function linhaCabazEmpresa(Corporate $empresa, string $periodo, ?int $taxId, float $taxValue): ?array
    {
        [$precoUnit, $qtd, $composicao] = $this->baseCabazEmpresa($empresa, $periodo, $taxId, $taxValue);

        if ($precoUnit <= 0 || $qtd <= 0) {
            return null;
        }

        // Valor liquido acordado (total da linha, sem IVA).
        $valorAcordadoLiquido = round($this->precoLiquido($precoUnit, $taxValue) * $qtd, 4);

        // Artigo COMPOSTO ja existente no Moloni (ex.: HM5069-0). As linhas-filhas
        // sao enviadas explicitamente (obrigatorio) com as quantidades reais.
        $referenciaComposto = (string) ($empresa->moloni_composto_ref ?: config('moloni.cabaz_composto_referencia', config('moloni.cabaz_reference', 'CABAZ')));
        $composto = $this->moloni->produtoPorReferencia($referenciaComposto);

        if ($composto === null) {
            throw new RuntimeException("Nao existe no Moloni um artigo composto com a referencia '{$referenciaComposto}'. Cria o cabaz composto no Moloni ou ajusta MOLONI_CABAZ_COMPOSTO_REFERENCIA no .env.");
        }

        // Qtd da linha-pai: nº de semanas do ciclo (a fatura mensal leva 4).
        $qtdLinha = (float) config('moloni.fatura_qtd_pai', 4);

        // O Moloni exige as linhas-filhas do artigo composto (child_products).
        // Levam as QUANTIDADES DO CICLO (por dia de entrega x semanas) e o
        // desconto (%) que faz o total igualar o valor acordado — tal como na
        // fatura manual.
        $filhos = $this->composto->linhasFilhas(
            compostoProductId: (int) $composto['product_id'],
            quantidades: $this->quantidadesCicloEmpresa($empresa),
            valorAcordadoLiquido: $valorAcordadoLiquido,
            taxId: $taxId,
            taxValue: $taxValue,
            periodo: $periodo,
            referenciaComposto: $referenciaComposto,
            qtyPai: $qtdLinha,
        );

        if ($filhos['child_products'] === []) {
            throw new RuntimeException("Nenhum produto da ficha de '{$empresa->empresa}' corresponde a composicao do artigo '{$referenciaComposto}' no Moloni. Confirma a composicao (php artisan moloni:artigo {$referenciaComposto}).");
        }

        $desconto = $filhos['desconto'];

        // O Moloni obriga: preco da linha-pai x qty = soma dos filhos.
        $precoTabela = $filhos['preco_pai'];
        $precoLinha = $precoTabela;

        // A linha leva o NOME DO ARTIGO ("Mix Frutas Corporativo"), como na
        // fatura manual. A empresa/sucursal identifica-se nas observacoes.
        $nome = trim((string) $composto['name']);

        $linha = [
            'product_id' => (int) $composto['product_id'],
            'name' => $nome !== '' ? $nome : trim($empresa->empresa.' '.($empresa->sucursal ?? '')),
            'qty' => $qtdLinha,
            'price' => $precoLinha,
            'discount' => $desconto,
            'order' => 1,
            'child_products' => $filhos['child_products'],
        ];

        if ($taxId !== null) {
            $linha['taxes'] = [[
                'tax_id' => $taxId,
                'value' => $taxValue,
                'order' => 1,
                'cumulative' => 0,
            ]];
        } else {
            $linha['exemption_reason'] = config('moloni.exemption_reason');
        }

        $totalComIva = round($precoUnit * $qtd, 2);

        return [
            'moloni' => array_filter($linha, fn ($v) => $v !== null && $v !== []),
            'resumo' => [
                'corporate_id' => $empresa->id,
                'empresa' => $empresa->empresa,
                'sucursal' => $empresa->sucursal,
                'quantidade' => $qtdLinha,
                'preco_tabela' => $precoTabela,
                'desconto_pct' => $desconto,
                'composicao_moloni' => $filhos['resumo'],
                'sem_correspondencia' => $filhos['sem_correspondencia'],
                'preco_unit_com_iva' => round($precoUnit, 2),
                'total_com_iva' => $totalComIva,
                'fruta_epoca' => $this->resolver->frutasEpoca($periodo),
                'produtos' => $composicao['resumo'],
            ],
            'total_com_iva' => $totalComIva,
        ];
    }

    /**
     * Quantidades do CICLO por chave interna (o que vai nas linhas-filhas do
     * artigo composto): soma por dia de entrega x nº de semanas do ciclo.
     * Exclui os produtos_mensais — esses ja vao em linhas proprias na fatura
     * (senao ficavam faturados a dobrar).
     *
     * @return array<string,float>
     */
    private function quantidadesCicloEmpresa(Corporate $empresa): array
    {
        $semanas = (float) config('moloni.fatura_semanas', 4);
        $dias = collect($empresa->dias_entrega ?? [])->filter()->values();
        $mensais = collect($empresa->produtos_mensais ?? [])->filter()->map(fn ($v): string => (string) $v)->all();

        $totais = [];

        if ($dias->isEmpty()) {
            // Sem dias de entrega configurados: usa a ficha base x semanas.
            foreach (($empresa->frutas ?? []) as $chave => $qtd) {
                $totais[(string) $chave] = (float) $qtd;
            }
        } else {
            foreach ($dias as $dia) {
                foreach ($empresa->frutasParaDia((string) $dia) as $chave => $qtd) {
                    $totais[(string) $chave] = ($totais[(string) $chave] ?? 0) + (float) $qtd;
                }
            }
        }

        $out = [];

        foreach ($totais as $chave => $qtd) {
            if (in_array((string) $chave, $mensais, true) || $qtd <= 0) {
                continue;
            }

            $out[(string) $chave] = round($qtd * $semanas, 2);
        }

        return $out;
    }

    /**
     * Bloco de observacoes de uma empresa/sucursal, no formato das faturas manuais.
     */
    private function observacoesEmpresa(Corporate $empresa, Carbon $inicio, Carbon $fim): string
    {
        $titulo = trim($empresa->fatura_nome ?: trim($empresa->empresa.' '.($empresa->sucursal ?? '')));

        $dias = collect($empresa->dias_entrega ?? [])->filter()->values();
        $horario = trim((string) ($empresa->horario_entrega ?? ''));

        $linhaEntregas = null;

        if ($dias->isNotEmpty()) {
            $linhaEntregas = 'Entregas: '.$dias->implode(' e ').($horario !== '' ? ' ('.$horario.')' : '');
        }

        $periodo = 'Período de fatura de '.$inicio->format('d/m').' a '.$fim->format('d/m/Y');

        // Pecas por entrega e total mensal (nº de entregas por semana x 4 semanas).
        $pecasEntrega = $dias->isNotEmpty() ? $empresa->totalPecasParaDia((string) $dias->first()) : 0;
        $entregasMes = $dias->count() * 4;
        $linhaPecas = $pecasEntrega > 0
            ? $pecasEntrega.' peças por entrega num total de '.($pecasEntrega * $entregasMes).' peças de fruta mensais'
            : null;

        $disclaimer = 'Alguns dos produtos descritos podem não corresponder exatamente aos entregues, porém o valor ref. e quantidades mantêm-se sempre conforme o acordado nesta colaboração.';

        return collect([$titulo, $linhaEntregas, $periodo, $linhaPecas, $disclaimer])
            ->filter()
            ->implode("\n");
    }

    /**
     * Linha de TRANSPORTE/PORTES da empresa: custo_envio (por entrega) x nº de
     * entregas do ciclo (dias de entrega x semanas). Leva o IVA da taxa normal
     * (23%), ao contrario da fruta (6%). Sem custo_envio na ficha, nao ha linha.
     *
     * @return array{moloni:array,resumo:array,total_com_iva:float}|null
     */
    private function linhaPortesEmpresa(Corporate $empresa): ?array
    {
        // Valor da ficha; se a ficha nao tiver nada, o valor por defeito das
        // Definicoes Moloni. Um 0 escrito na ficha isenta a empresa de portes.
        $valorEntrega = $empresa->custo_envio !== null
            ? (float) $empresa->custo_envio
            : (float) (config('moloni.custo_envio_padrao') ?? 0);

        if ($valorEntrega <= 0) {
            return null;
        }

        $semanas = (float) config('moloni.fatura_semanas', 4);
        $entregas = round(collect($empresa->dias_entrega ?? [])->filter()->count() * $semanas, 2);

        if ($entregas <= 0) {
            throw new RuntimeException("A empresa '{$empresa->empresa}' tem custo de envio definido mas nao tem dias de entrega — nao da para calcular o numero de entregas do ciclo.");
        }

        $referenciaPortes = trim((string) config('moloni.portes_referencia'));

        if ($referenciaPortes === '') {
            throw new RuntimeException("A empresa '{$empresa->empresa}' tem custo de envio definido mas falta configurar MOLONI_PORTES_REFERENCIA no .env (artigo do Moloni para os portes).");
        }

        $artigo = $this->moloni->produtoPorReferencia($referenciaPortes);

        if ($artigo === null) {
            throw new RuntimeException("Nao existe no Moloni um artigo com a referencia '{$referenciaPortes}' para a linha de transporte.");
        }

        $taxValue = (float) config('moloni.portes_tax_value', 23);
        $taxId = filled(config('moloni.portes_tax_id'))
            ? (int) config('moloni.portes_tax_id')
            : $this->moloni->taxIdPorValor($taxValue);

        if ($taxId === null) {
            throw new RuntimeException("Nao foi possivel encontrar no Moloni a taxa de IVA de {$taxValue}% para os portes. Define MOLONI_PORTES_TAX_ID no .env.");
        }

        $precoLiquido = $this->precoLiquido($valorEntrega, $taxValue);

        $linha = [
            'product_id' => (int) $artigo['product_id'],
            'name' => (string) $artigo['name'],
            'qty' => $entregas,
            'price' => round($precoLiquido, 4),
            'order' => 1,
            'taxes' => [[
                'tax_id' => $taxId,
                'value' => $taxValue,
                'order' => 1,
                'cumulative' => 0,
            ]],
        ];

        $totalComIva = round($valorEntrega * $entregas, 2);

        return [
            'moloni' => $linha,
            'resumo' => [
                'corporate_id' => $empresa->id,
                'transporte' => [
                    'artigo' => $artigo['name'],
                    'referencia' => $referenciaPortes,
                    'valor_entrega_com_iva' => round($valorEntrega, 2),
                    'entregas' => $entregas,
                    'iva' => $taxValue,
                    'total_com_iva' => $totalComIva,
                ],
            ],
            'total_com_iva' => $totalComIva,
        ];
    }

    /**
     * Linhas de fatura para os OUTROS produtos da empresa (pastelaria e produtos
     * marcados como mensais na ficha), alem do cabaz de fruta (composto).
     * Preco vem do mapeamento faturacao_mapa_produtos (Setting) via resolver;
     * quantidade do ciclo = soma por dia de entrega x 4 semanas. Produtos sem
     * preco definido sao ignorados (ficam listados no resumo para configurar).
     *
     * @return array{moloni:array<int,array>,resumo:array<int,array>,total_com_iva:float}
     */
    private function linhasExtrasEmpresa(Corporate $empresa, string $periodo, ?int $taxId, float $taxValue): array
    {
        $mensais = collect($empresa->produtos_mensais ?? [])->filter()->unique()->values();

        if ($mensais->isEmpty()) {
            return ['moloni' => [], 'resumo' => [], 'total_com_iva' => 0.0];
        }

        $dias = collect($empresa->dias_entrega ?? [])->filter()->values();
        $semanas = 4;

        $moloni = [];
        $resumo = [];
        $totalComIva = 0.0;

        foreach ($mensais as $chave) {
            $chave = (string) $chave;

            // Quantidade no ciclo = soma por dia de entrega x semanas.
            $qtdCiclo = 0.0;

            foreach ($dias as $dia) {
                $frutas = $empresa->frutasParaDia((string) $dia);
                $pastelaria = $empresa->pastelariaPorDia((string) $dia);
                $qtdCiclo += (float) ($frutas[$chave] ?? $pastelaria[$chave] ?? 0);
            }

            $qtdCiclo = round($qtdCiclo * $semanas, 2);

            if ($qtdCiclo <= 0) {
                continue;
            }

            $resolvido = $this->resolver->resolver($chave, $periodo);
            $precoComIva = (float) ($resolvido['preco'] ?? 0);

            if ($precoComIva <= 0) {
                // Sem preco no mapeamento: nao fatura, mas regista para configurar.
                $resumo[] = ['produto' => $resolvido['nome'], 'quantidade' => $qtdCiclo, 'preco_unit_com_iva' => 0, 'sem_preco' => true];

                continue;
            }

            $precoLiquido = $this->precoLiquido($precoComIva, $taxValue);
            $productId = $this->moloni->obterOuCriarProduto($resolvido['referencia'], $resolvido['nome'], $precoLiquido, $taxId);

            $linha = [
                'product_id' => $productId,
                'name' => $resolvido['nome'],
                'qty' => $qtdCiclo,
                'price' => $precoLiquido,
                'order' => 1,
            ];

            if ($taxId !== null) {
                $linha['taxes'] = [[
                    'tax_id' => $taxId,
                    'value' => $taxValue,
                    'order' => 1,
                    'cumulative' => 0,
                ]];
            } else {
                $linha['exemption_reason'] = config('moloni.exemption_reason');
            }

            $moloni[] = array_filter($linha, fn ($v) => $v !== null && $v !== []);
            $totalComIva += round($precoComIva * $qtdCiclo, 2);
            $resumo[] = ['produto' => $resolvido['nome'], 'quantidade' => $qtdCiclo, 'preco_unit_com_iva' => round($precoComIva, 2)];
        }

        return ['moloni' => $moloni, 'resumo' => $resumo, 'total_com_iva' => round($totalComIva, 2)];
    }

    /**
     * Determina preco unitario (com IVA, base site), quantidade no periodo e
     * composicao do cabaz de uma empresa.
     *
     * @return array{0:float,1:float,2:array{moloni:array,resumo:array}}
     */
    private function baseCabazEmpresa(Corporate $empresa, string $periodo, ?int $taxId, float $taxValue): array
    {
        // Nº de entregas e de semanas do CICLO de faturacao. Tudo o que a ficha
        // guarda e por entrega ou por semana; a fatura e do ciclo inteiro.
        $semanas = (float) config('moloni.fatura_semanas', 4);
        $entregas = max(1.0, collect($empresa->dias_entrega ?? [])->filter()->count() * $semanas);

        // 1) Valor acordado para o ciclo (o que sai na fatura). Manda em tudo.
        $valorCiclo = (float) ($empresa->valor_ciclo ?? 0);

        if ($valorCiclo > 0) {
            $composicao = $empresa->usaCabazTipo()
                ? $this->composicaoListaCabaz($empresa->cabaz_tipo, $periodo, $taxId, $taxValue)
                : $this->composicaoFrutasEmpresa($empresa, $periodo, $taxId, $taxValue);

            return [$valorCiclo, 1.0, $composicao];
        }

        // Preco acordado por cabaz (com IVA), por ENTREGA.
        $precoManual = (float) ($empresa->preco_cabaz ?? 0);

        if ($empresa->usaCabazTipo()) {
            $cabazesPorEntrega = (float) max(1, (int) ($empresa->cabaz_quantidade ?? 1));
            // Composicao a partir da lista semanal do tipo (produtos do cabaz do site).
            $composicao = $this->composicaoListaCabaz($empresa->cabaz_tipo, $periodo, $taxId, $taxValue);

            if ($precoManual > 0) {
                $precoUnit = $precoManual;
            } elseif (($composicao['total_liquido'] ?? 0) > 0) {
                $precoUnit = $this->comIva($composicao['total_liquido'], $taxValue);
            } else {
                $precoUnit = $this->precoSiteCabaz($empresa->cabaz_tipo);
            }

            // qtd = cabazes por entrega x entregas do ciclo.
            return [$precoUnit, $cabazesPorEntrega * $entregas, $composicao];
        }

        // Empresa com frutas individuais.
        $composicao = $this->composicaoFrutasEmpresa($empresa, $periodo, $taxId, $taxValue);

        if ($precoManual > 0) {
            return [$precoManual, $entregas, $composicao];
        }

        if (($composicao['total_liquido'] ?? 0) > 0) {
            // A composicao e por entrega (frutas da ficha).
            return [$this->comIva($composicao['total_liquido'], $taxValue), $entregas, $composicao];
        }

        // Preco por peca x pecas da semana x semanas do ciclo.
        // ATENCAO: preco_venda_peca e LIQUIDO (sem IVA). Como o resto do fluxo
        // trabalha com valores com IVA, acrescenta-se aqui o IVA para que a
        // conversao seguinte devolva exatamente o liquido da ficha.
        $precoPeca = (float) ($empresa->preco_venda_peca ?? 0);
        $pecas = $empresa->totalPecasPorSemana();

        return [$this->comIva(round($precoPeca * max(0, $pecas), 2), $taxValue), $semanas, $composicao];
    }

    private function comIva(float $liquido, float $taxValue): float
    {
        return round($liquido * (1 + ($taxValue > 0 ? $taxValue / 100 : 0)), 4);
    }

    // ==================================================================
    //  Composicao / linhas partilhadas
    // ==================================================================

    /**
     * Agrega uma colecao de nomes/chaves de produtos numa composicao Moloni
     * (child_products) + resumo legivel, resolvendo nomes variaveis.
     *
     * @return array{moloni:array<int,array>,resumo:array<int,array>}
     */
    private function agregarComposicao(Collection $produtos, string $periodo, ?int $taxId, float $taxValue, ?callable $precoPorChave = null): array
    {
        $contagem = [];

        foreach ($produtos as $chaveOuNome) {
            if (blank($chaveOuNome)) {
                continue;
            }

            $chave = (string) $chaveOuNome;
            $resolvido = $this->resolver->resolver($chave, $periodo);
            $ref = $resolvido['referencia'];
            // Preco unitario (com IVA, base site) por sub-produto: preferencia
            // para o preco definido no mapeamento; senao o callback fornecido.
            $precoComIva = $resolvido['preco'] ?? ($precoPorChave !== null ? (float) $precoPorChave($chave) : 0.0);

            if (! isset($contagem[$ref])) {
                $contagem[$ref] = ['nome' => $resolvido['nome'], 'referencia' => $ref, 'qty' => 0, 'preco_com_iva' => $precoComIva];
            }

            $contagem[$ref]['qty']++;

            if ((float) $contagem[$ref]['preco_com_iva'] <= 0 && $precoComIva > 0) {
                $contagem[$ref]['preco_com_iva'] = $precoComIva;
            }
        }

        $moloni = [];
        $resumo = [];
        $totalLiquido = 0.0;
        $ordem = 1;

        foreach ($contagem as $item) {
            $precoLiquidoUnit = $this->precoLiquido((float) $item['preco_com_iva'], $taxValue);
            $productId = $this->moloni->obterOuCriarProduto($item['referencia'], $item['nome'], $precoLiquidoUnit, $taxId);

            // Cada sub-produto leva o seu proprio preco; o total do cabaz e a soma.
            // Campos minimos do child_product (o imposto fica na linha-pai).
            $moloni[] = [
                'product_id' => $productId,
                'name' => $item['nome'],
                'qty' => (float) $item['qty'],
                'price' => $precoLiquidoUnit,
                'order' => $ordem++,
            ];
            $totalLiquido += $precoLiquidoUnit * (float) $item['qty'];
            $resumo[] = [
                'nome' => $item['nome'],
                'quantidade' => $item['qty'],
                'preco_unit_com_iva' => round((float) $item['preco_com_iva'], 2),
            ];
        }

        return ['moloni' => $moloni, 'resumo' => $resumo, 'total_liquido' => round($totalLiquido, 4)];
    }

    /**
     * Composicao a partir dos itens da ListaCabaz de um tipo (empresas com cabaz_tipo).
     */
    private function composicaoListaCabaz(string $tipo, string $periodo, ?int $taxId, float $taxValue): array
    {
        [$ano, $mes] = array_map('intval', explode('-', $periodo));

        $lista = \App\Models\ListaCabaz::query()
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->latest('semana_numero')
            ->first();

        if ($lista === null) {
            return ['moloni' => [], 'resumo' => []];
        }

        $nomes = $lista->itensPorTipo($tipo)->pluck('produto');

        return $this->agregarComposicao($nomes, $periodo, $taxId, $taxValue);
    }

    /**
     * Composicao a partir das frutas configuradas numa empresa (chaves internas).
     * O kiwi e somado a fruta da epoca (o resolver mapeia kiwi -> fruta_epoca).
     */
    private function composicaoFrutasEmpresa(Corporate $empresa, string $periodo, ?int $taxId, float $taxValue): array
    {
        $nomes = collect($empresa->frutas ?? [])
            ->filter(fn ($qtd): bool => (float) $qtd > 0)
            ->flatMap(fn ($qtd, string $chave): array => array_fill(0, (int) round((float) $qtd), $chave));

        // Preco por peca da empresa aplicado a cada sub-produto. O campo e
        // LIQUIDO (sem IVA); a agregacao espera precos com IVA.
        $precoPeca = (float) ($empresa->preco_venda_peca ?? 0);

        return $this->agregarComposicao(
            $nomes,
            $periodo,
            $taxId,
            $taxValue,
            $precoPeca > 0 ? fn (string $chave): float => $this->comIva($precoPeca, $taxValue) : null,
        );
    }

    /**
     * Monta uma linha de documento com o cabaz composto (child_products).
     */
    private function linhaCabaz(string $nome, float $precoLiquido, ?int $taxId, float $taxValue, array $childProducts, float $qty = 1.0): array
    {
        $cabazProductId = $this->moloni->obterOuCriarProduto(
            (string) config('moloni.cabaz_reference', 'CABAZ'),
            (string) config('moloni.cabaz_name', 'Cabaz de fruta e legumes'),
            $precoLiquido,
            $taxId,
        );

        $linha = [
            'product_id' => $cabazProductId,
            'name' => $nome,
            'qty' => $qty,
            'price' => $precoLiquido,
            'order' => 1,
        ];

        if ($taxId !== null) {
            $linha['taxes'] = [[
                'tax_id' => $taxId,
                'value' => $taxValue,
                'order' => 1,
                'cumulative' => 0,
            ]];
        } else {
            $linha['exemption_reason'] = config('moloni.exemption_reason');
        }

        if ($childProducts !== []) {
            $linha['child_products'] = $childProducts;
        }

        return array_filter($linha, fn ($v) => $v !== null && $v !== []);
    }

    private function pagamentosFaturaRecibo(float $totalPago, ?int $metodoId = null): array
    {
        // Metodo escolhido na emissao tem prioridade; senao usa o do .env / automatico.
        $metodo = $metodoId ?: $this->moloni->paymentMethodId();

        if ($metodo === null) {
            throw new RuntimeException('Nao foi possivel obter um metodo de pagamento no Moloni. Crie um metodo de pagamento na conta Moloni ou defina MOLONI_PAYMENT_METHOD_ID no .env.');
        }

        return [[
            'payment_method_id' => $metodo,
            'date' => now()->toDateString(),
            'value' => round($totalPago, 2),
        ]];
    }

    // ==================================================================
    //  Helpers de dados de cliente
    // ==================================================================

    private function clienteDeWooOrder(WooOrder $order): array
    {
        $billing = $order->raw_payload['billing'] ?? [];

        return [
            'name' => $order->billing_name ?: trim(($billing['first_name'] ?? '').' '.($billing['last_name'] ?? '')) ?: 'Consumidor final',
            'vat' => $this->nifDeWooOrder($order),
            'email' => $order->billing_email ?: ($billing['email'] ?? ''),
            'address' => trim(($billing['address_1'] ?? '').' '.($billing['address_2'] ?? '')),
            'city' => $billing['city'] ?? '',
            'zip_code' => $billing['postcode'] ?? '',
            'language_id' => $order->prefersEnglish() ? 2 : 1,
        ];
    }

    private function nifDeWooOrder(WooOrder $order): ?string
    {
        $billing = $order->raw_payload['billing'] ?? [];

        foreach (['vat', 'nif', 'vat_number'] as $campo) {
            if (filled($billing[$campo] ?? null)) {
                return (string) $billing[$campo];
            }
        }

        foreach ($order->raw_payload['meta_data'] ?? [] as $meta) {
            $chave = strtolower((string) ($meta['key'] ?? ''));

            if (in_array($chave, ['_billing_nif', '_billing_vat', 'nif', 'vat'], true) && filled($meta['value'] ?? null)) {
                return (string) $meta['value'];
            }
        }

        return null;
    }

    private function clienteDeCorporate(Corporate $empresa): array
    {
        return [
            'name' => $empresa->fatura_nome ?: $empresa->empresa,
            'vat' => $empresa->fatura_nif,
            'email' => $empresa->fatura_email ?? '',
            'address' => $empresa->fatura_morada ?? '',
            'city' => '',
            'zip_code' => '',
            'language_id' => 1,
        ];
    }

    // ==================================================================
    //  Precos / periodos
    // ==================================================================

    /**
     * Preco de venda no site de um tipo de cabaz (WooProduct correspondente).
     */
    private function precoSiteCabaz(?string $tipo): float
    {
        if (blank($tipo)) {
            return 0.0;
        }

        $termos = match ($tipo) {
            'mini' => ['solo', 'mini'],
            'pequeno' => ['pequeno'],
            'medio' => ['medio', 'médio'],
            'grande' => ['grande'],
            default => [$tipo],
        };

        $produto = WooProduct::query()
            ->where(function ($query) use ($termos): void {
                foreach ($termos as $termo) {
                    $query->orWhere('name', 'like', "%{$termo}%");
                }
            })
            ->get()
            ->first(fn (WooProduct $p): bool => WooOrder::detectarCabazTipo([['name' => $p->name]]) === $tipo);

        return $produto?->precoVenda() ?? 0.0;
    }

    /**
     * Converte um preco (com IVA, base site) para preco liquido (sem IVA).
     */
    private function precoLiquido(float $precoComIva, float $taxValue): float
    {
        if (! (bool) config('moloni.precos_incluem_iva', true) || $taxValue <= 0) {
            return round($precoComIva, 4);
        }

        return round($precoComIva / (1 + $taxValue / 100), 4);
    }

    private function nomeCabazSubscricao(WooOrder $order): string
    {
        $tipo = $order->cabaz_tipo ?? WooOrder::detectarCabazTipo($order->line_items ?? []);
        $sufixo = $tipo ? ' '.ucfirst($tipo) : '';

        return 'Cabaz de subscricao'.$sufixo;
    }

    private function periodoLegivel(string $periodo): string
    {
        try {
            return Carbon::createFromFormat('Y-m', $periodo)->translatedFormat('F Y');
        } catch (\Throwable) {
            return $periodo;
        }
    }
}
