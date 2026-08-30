<?php

namespace App\Services;

use App\Models\Corporate;
use App\Support\CabazProdutoResolver;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Emite Guias de Transporte (Moloni billsOfLading) para as entregas corporate.
 * Usa o MESMO artigo composto da fatura (o Moloni expande os componentes) e
 * mete o valor da ficha na linha. A designacao leva as quantidades exatas do
 * dia (obrigatorio por lei). Matricula + expedicao "Nossa Viatura".
 * Chamada automaticamente quando a preparacao do dia e marcada como feita.
 */
class GuiaTransporteService
{
    private const DIAS = [
        1 => 'Segunda', 2 => 'Terca', 3 => 'Quarta', 4 => 'Quinta', 5 => 'Sexta', 6 => 'Sabado',
    ];

    private const KG_KEYS = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

    public function __construct(
        private readonly MoloniService $moloni,
        private readonly CabazProdutoResolver $resolver,
        private readonly CompostoCabazService $composto,
    ) {}

    /**
     * @return array{document_id:int}
     */
    public function emitirGuiaCorporate(Corporate $corporate, Carbon $data, string $matricula, ?string $dia = null): array
    {
        $matricula = trim($matricula);

        if ($matricula === '') {
            throw new RuntimeException('Falta a matricula do carro para emitir a guia de transporte.');
        }

        $documentSetId = $this->moloni->documentSetId('guia');

        if ($documentSetId === null) {
            throw new RuntimeException('Falta configurar a Serie da Guia de Transporte nas Definicoes Moloni.');
        }

        $payload = $this->montarPayload($corporate, $data, $documentSetId, $matricula, $dia);

        return ['document_id' => $this->moloni->inserirGuiaTransporte($payload)['document_id']];
    }

    /**
     * Guia de Remessa, para as sucursais em que quem entrega e um terceiro.
     * Sai ALEM da guia de transporte, com os mesmos produtos do dia, mas sem
     * matricula nossa — o transportador vai nas observacoes.
     *
     * @return array{document_id:int}
     */
    public function emitirGuiaRemessaCorporate(Corporate $corporate, Carbon $data, ?string $dia = null): array
    {
        $documentSetId = $this->moloni->documentSetId('remessa');

        if ($documentSetId === null) {
            throw new RuntimeException('Falta configurar a Serie da Guia de Remessa nas Definicoes Moloni.');
        }

        $payload = $this->montarPayload($corporate, $data, $documentSetId, null, $dia);

        $transportador = trim((string) $corporate->transportador);

        if ($transportador !== '') {
            $payload['notes'] = trim($payload['notes']."\nTransporte efetuado por: ".$transportador);
        }

        return ['document_id' => $this->moloni->inserirGuiaRemessa($payload)['document_id']];
    }

    /**
     * O corpo comum aos dois documentos. A matricula so entra quando ha uma
     * (guia de transporte); na remessa fica de fora.
     *
     * @return array<string, mixed>
     */
    private function montarPayload(Corporate $corporate, Carbon $data, int $documentSetId, ?string $matricula, ?string $dia): array
    {
        $dia ??= self::DIAS[$data->dayOfWeek] ?? 'Segunda';

        // Mesmo artigo COMPOSTO da fatura. Por empresa (moloni_guia_ref), senao
        // .env, senao o composto da fatura.
        $referenciaGuia = trim((string) (
            $corporate->moloni_guia_ref
            ?: config('moloni.guia_referencia')
            ?: $corporate->moloni_composto_ref
            ?: config('moloni.cabaz_composto_referencia', config('moloni.cabaz_reference', 'CABAZ'))
        ));

        $artigo = $this->moloni->produtoPorReferencia($referenciaGuia);

        if ($artigo === null) {
            throw new RuntimeException("Nao existe no Moloni um artigo com a referencia '{$referenciaGuia}' para a guia. Cria o artigo ou define MOLONI_GUIA_REFERENCIA / a referencia na ficha da empresa.");
        }

        // Designacao com as QUANTIDADES EXATAS do dia (da ficha).
        $partes = [];
        $quantidadesDia = [];

        foreach ($corporate->frutasParaDia($dia) as $chave => $quantidade) {
            $quantidade = (float) $quantidade;

            if ($quantidade <= 0) {
                continue;
            }

            $quantidadesDia[(string) $chave] = $quantidade;

            $label = $this->resolver->resolver((string) $chave, $data->format('Y-m'))['nome'];

            if (in_array((string) $chave, self::KG_KEYS, true)) {
                $partes[] = ((int) round($quantidade * 1000)).'g '.$label;
            } else {
                $partes[] = ((int) round($quantidade)).' '.$label;
            }
        }

        $designacao = (string) $artigo['name'];

        if ($partes !== []) {
            $designacao .= ' - '.implode(' + ', $partes);
        }

        $taxValue = (float) config('moloni.default_tax_value', 6);
        $incluiIva = (bool) config('moloni.precos_incluem_iva', true);
        $paraLiquido = fn (float $v): float => ($incluiIva && $taxValue > 0) ? $v / (1 + $taxValue / 100) : $v;

        // Valor da GUIA (uma entrega), por ordem de prioridade:
        //  1. valor acordado do ciclo / nº de entregas do ciclo
        //  2. preco_cabaz (com IVA) x cabazes por entrega
        //  3. pecas do dia x preco de venda por peca (este ja e LIQUIDO)
        $valorCiclo = (float) ($corporate->valor_ciclo ?? 0);
        $precoCabaz = (float) ($corporate->preco_cabaz ?? 0);

        if ($valorCiclo > 0) {
            $entregas = max(1.0, collect($corporate->dias_entrega ?? [])->filter()->count() * (float) config('moloni.fatura_semanas', 4));
            $valorLiquido = $paraLiquido($valorCiclo / $entregas);
        } elseif ($precoCabaz > 0) {
            $valorLiquido = $paraLiquido($precoCabaz * max(1, (int) ($corporate->cabaz_quantidade ?? 1)));
        } else {
            // preco_venda_peca e LIQUIDO (sem IVA): nao se converte.
            $valorLiquido = (float) ($corporate->valorVendaParaDia($dia) ?? 0);
        }

        // Linha = mesmo artigo COMPOSTO da fatura. O Moloni exige os filhos
        // (child_products); vao com as quantidades REAIS do dia e o desconto
        // que faz o total bater certo com o valor da ficha.
        $taxId = $this->moloni->taxId();

        $filhos = $this->composto->linhasFilhas(
            compostoProductId: (int) $artigo['product_id'],
            quantidades: $quantidadesDia,
            valorAcordadoLiquido: round($valorLiquido, 4),
            taxId: $taxId,
            taxValue: $taxValue,
            periodo: $data->format('Y-m'),
            referenciaComposto: $referenciaGuia,
            qtyPai: 1.0,
        );

        if ($filhos['child_products'] === []) {
            throw new RuntimeException("Nenhum produto do dia corresponde a composicao do artigo '{$referenciaGuia}' no Moloni. Confirma a composicao do artigo (php artisan moloni:artigo {$referenciaGuia}).");
        }

        $linha = [
            'product_id' => (int) $artigo['product_id'],
            'name' => $designacao,
            // Guia = 1 entrega.
            'qty' => 1,
            // O Moloni obriga: preco da linha-pai x qty = soma dos filhos.
            // O ajuste ao valor da ficha e feito pelo desconto (%).
            'price' => $filhos['preco_pai'],
            'order' => 1,
            'child_products' => $filhos['child_products'],
        ];

        if ($filhos['desconto'] > 0) {
            $linha['discount'] = $filhos['desconto'];
        }

        if ($taxId !== null) {
            $linha['taxes'] = [[
                'tax_id' => $taxId,
                'value' => $taxValue,
                'order' => 1,
                'cumulative' => 0,
            ]];
        } else {
            $linha['exemption_reason'] = config('moloni.exemption_reason') ?: 'M99';
        }

        if (($warehouseId = $this->moloni->warehouseId()) !== null) {
            $linha['warehouse_id'] = $warehouseId;
        }

        $hora = (string) config('moloni.guia_hora_transporte', '08:00');

        $destino = $this->partesMorada($corporate->moradaParaEntrega());

        if (filled($corporate->cp_entrega)) {
            $destino['zip_code'] = trim((string) $corporate->cp_entrega);
        }

        if (filled($corporate->cidade_entrega)) {
            $destino['city'] = trim((string) $corporate->cidade_entrega);
        }

        $payload = [
            'date' => now()->toDateString(),
            'document_set_id' => $documentSetId,
            'customer_id' => $this->moloni->obterOuCriarCliente($this->cliente($corporate)),
            'status' => config('moloni.fechar_documentos') ? 1 : 0,
            'products' => [$linha],
            'notes' => (string) config('moloni.guia_observacoes'),
            'delivery_departure_address' => (string) config('moloni.guia_morada_carga'),
            'delivery_departure_city' => (string) config('moloni.guia_cidade_carga'),
            'delivery_departure_zip_code' => (string) config('moloni.guia_cp_carga'),
            'delivery_departure_country' => 1,
            'delivery_destination_address' => $destino['address'],
            'delivery_destination_zip_code' => $destino['zip_code'],
            'delivery_destination_city' => $destino['city'],
            'delivery_destination_country' => 1,
            'delivery_datetime' => now()->setTimeFromTimeString($hora)->format('Y-m-d H:i:s'),
        ];

        if (filled($matricula)) {
            $payload['vehicle_name'] = $matricula;
            $payload['vehicle_number_plate'] = $matricula;
        }

        $metodoExpedicaoId = (int) config('moloni.guia_delivery_method_id', 0);

        if ($metodoExpedicaoId > 0) {
            $payload['delivery_method_id'] = $metodoExpedicaoId;
        }

        return $payload;
    }

    /**
     * Separa uma morada portuguesa em [address, zip_code, city], detetando o
     * codigo postal (NNNN-NNN). Necessario para a guia (campos obrigatorios).
     *
     * @return array{address:string,zip_code:string,city:string}
     */
    private function partesMorada(?string $morada): array
    {
        $morada = trim((string) $morada);

        if ($morada === '') {
            return ['address' => '', 'zip_code' => '', 'city' => ''];
        }

        if (preg_match('/(\d{4}-\d{3})/', $morada, $m, PREG_OFFSET_CAPTURE)) {
            $zip = $m[1][0];
            $pos = (int) $m[1][1];
            $endereco = rtrim(substr($morada, 0, $pos), " ,");
            $cidade = trim(ltrim(substr($morada, $pos + strlen($zip)), " ,"));

            return [
                'address' => $endereco !== '' ? $endereco : $morada,
                'zip_code' => $zip,
                'city' => $cidade !== '' ? $cidade : ($endereco !== '' ? $endereco : $morada),
            ];
        }

        $partes = array_values(array_filter(array_map('trim', explode(',', $morada)), fn ($x): bool => $x !== ''));

        return [
            'address' => $morada,
            'zip_code' => '',
            'city' => $partes !== [] ? (string) end($partes) : $morada,
        ];
    }

    private function cliente(Corporate $corporate): array
    {
        return [
            'name' => $corporate->fatura_nome ?: trim($corporate->empresa.' '.($corporate->sucursal ?? '')),
            'vat' => $corporate->fatura_nif,
            'email' => $corporate->fatura_email ?? '',
            'address' => $corporate->fatura_morada ?? '',
            'city' => '',
            'zip_code' => '',
            'language_id' => 1,
        ];
    }
}
