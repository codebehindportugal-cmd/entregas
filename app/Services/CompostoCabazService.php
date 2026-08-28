<?php

namespace App\Services;

use App\Support\CabazProdutoResolver;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Constroi as linhas-filhas (`child_products`) de um artigo COMPOSTO do Moloni
 * (ex.: HM5069-0 "Mix Frutas Corporativo") com as QUANTIDADES REAIS da ficha.
 *
 * Porque e preciso: o Moloni recusa qualquer documento cuja linha aponte para
 * um artigo composto sem os filhos ("Field 'child_products' is required").
 *
 * Como funciona:
 *  1. Le a composicao do artigo no Moloni (referencias, nomes e precos-tabela).
 *  2. Faz o casamento entre cada filho e as chaves internas da ficha
 *     (banana, maca, pera, laranja, uvas, fruta_epoca, ...).
 *  3. Poe em cada filho a quantidade real (do dia, na guia; do mes, na fatura).
 *  4. Filhos sem quantidade sao OMITIDOS da linha.
 *  5. Calcula o desconto (%) que faz o total dos filhos igualar o valor
 *     acordado — e o mesmo desconto que aparece na fatura manual.
 */
class CompostoCabazService
{
    /**
     * Palavras que identificam cada chave interna no nome/referencia do filho.
     * A ordem importa: as chaves mais especificas sao testadas primeiro.
     *
     * @var array<string,array<int,string>>
     */
    private const TOKENS = [
        'morangos' => ['morango'],
        'mirtilos' => ['mirtilo'],
        'framboesas' => ['framboesa'],
        'amoras' => ['amora'],
        'frutos_secos' => ['frutos secos', 'frutosecos'],
        'uvas' => ['uva'],
        'banana' => ['banana'],
        'maca' => ['maca'],
        'pera' => ['pera'],
        'laranja' => ['laranja'],
        'pao_mistura' => ['pao de mistura', 'pao mistura'],
        'pao_forma' => ['pao de forma', 'pao forma'],
        'croissant' => ['croissant'],
        'bolo' => ['bolo'],
        'fruta_epoca' => ['fruta da epoca', 'fruta epoca', 'epoca'],
    ];

    public function __construct(
        private readonly MoloniService $moloni,
        private readonly CabazProdutoResolver $resolver,
    ) {}

    /**
     * @param  array<string,float>  $quantidades  chave interna => quantidade total a enviar
     * @return array{
     *     child_products: array<int,array<string,mixed>>,
     *     bruto_liquido: float,
     *     preco_pai: float,
     *     desconto: float,
     *     resumo: array<int,array<string,mixed>>,
     *     sem_correspondencia: array<int,string>
     * }
     */
    public function linhasFilhas(
        int $compostoProductId,
        array $quantidades,
        float $valorAcordadoLiquido,
        ?int $taxId,
        float $taxValue,
        ?string $periodo = null,
        string $referenciaComposto = '',
        float $qtyPai = 1.0,
    ): array {
        $componentes = $this->moloni->componentesComposto($compostoProductId);

        if ($componentes === []) {
            throw new RuntimeException(
                'Nao foi possivel ler a composicao do artigo composto '.($referenciaComposto !== '' ? "'{$referenciaComposto}' " : '')
                ."(product_id {$compostoProductId}) no Moloni. Corre `php artisan moloni:artigo {$referenciaComposto}` para ver o que a API devolve."
            );
        }

        // Quantidades relevantes (> 0), por chave normalizada.
        $pendentes = [];

        foreach ($quantidades as $chave => $qtd) {
            $qtd = (float) $qtd;

            if ($qtd > 0) {
                $pendentes[$this->normalizarChave((string) $chave)] = $qtd;
            }
        }

        $nomesEpoca = array_map(fn (string $n): string => $this->normalizar($n), $this->resolver->frutasEpoca($periodo));

        $linhas = [];
        $resumo = [];
        $bruto = 0.0;
        $ordem = 1;

        foreach ($componentes as $filho) {
            $chave = $this->chaveDoFilho($filho, array_keys($pendentes), $nomesEpoca);

            if ($chave === null || ! isset($pendentes[$chave])) {
                continue; // filho do composto que nao vai nesta entrega/ciclo
            }

            $qtd = (float) $pendentes[$chave];
            unset($pendentes[$chave]);

            $preco = (float) $filho['price'];
            $bruto += $preco * $qtd;

            $nomeLinha = $this->nomeLinha($chave, (string) $filho['name'], $periodo);

            $linhas[] = [
                'product_id' => (int) $filho['product_id'],
                'name' => $nomeLinha,
                'qty' => round($qtd, 4),
                'price' => round($preco, 4),
                'order' => $ordem++,
            ];

            $resumo[] = [
                'chave' => $chave,
                'referencia' => $filho['reference'],
                'nome' => $nomeLinha,
                'quantidade' => round($qtd, 2),
                'preco_tabela' => round($preco, 4),
            ];
        }

        $bruto = round($bruto, 4);

        // Desconto (%) que faz o total dos filhos bater certo com o acordado.
        $desconto = 0.0;

        if ($bruto > 0 && $valorAcordadoLiquido > 0) {
            $desconto = max(0.0, min(100.0, round((1 - ($valorAcordadoLiquido / $bruto)) * 100, 2)));
        }

        $warehouseId = $this->moloni->warehouseId();

        foreach ($linhas as $i => $linha) {
            if ($desconto > 0) {
                $linhas[$i]['discount'] = $desconto;
            }

            if ($taxId !== null) {
                $linhas[$i]['taxes'] = [[
                    'tax_id' => $taxId,
                    'value' => $taxValue,
                    'order' => 1,
                    'cumulative' => 0,
                ]];
            } else {
                $linhas[$i]['exemption_reason'] = config('moloni.exemption_reason') ?: 'M99';
            }

            if ($warehouseId !== null) {
                $linhas[$i]['warehouse_id'] = $warehouseId;
            }
        }

        return [
            'child_products' => $linhas,
            'bruto_liquido' => $bruto,
            // O Moloni OBRIGA: preco da linha-pai x qty = soma dos filhos.
            'preco_pai' => round($bruto / ($qtyPai > 0 ? $qtyPai : 1.0), 4),
            'desconto' => $desconto,
            'resumo' => $resumo,
            'sem_correspondencia' => array_keys($pendentes),
        ];
    }

    /**
     * Nome da linha-filha no documento. Para a FRUTA DA EPOCA acrescenta a
     * fruta concreta do mes ao nome generico do artigo, para o documento dizer
     * o que foi mesmo entregue: "Fruta da epoca 250g (...) — Ameixa".
     * A fruta do mes vem do mapeamento faturacao_mapa_produtos
     * (`php artisan moloni:fruta-epoca "Ameixa"`).
     */
    private function nomeLinha(string $chave, string $nomeArtigo, ?string $periodo): string
    {
        if ($chave !== 'fruta_epoca') {
            return $nomeArtigo;
        }

        $frutas = $this->resolver->frutasEpoca($periodo);
        $concreto = trim(implode(' + ', $frutas));

        if ($concreto === '') {
            return $nomeArtigo;
        }

        // Ja esta la o nome concreto? Nao repete. Ignora o que esta entre
        // parenteses, que no artigo do Moloni e a lista de frutas possiveis
        // ("Fruta da epoca 250g (morango ou clementina ou ...)").
        $semParenteses = (string) preg_replace('/\([^)]*\)/', ' ', $nomeArtigo);

        if (str_contains($this->normalizar($semParenteses), $this->normalizar($concreto))) {
            return $nomeArtigo;
        }

        return $nomeArtigo.' — '.$concreto;
    }

    /**
     * Descobre a que chave interna corresponde um filho do composto.
     *
     * @param  array{product_id:int,reference:string,name:string,qty:float,price:float}  $filho
     * @param  array<int,string>  $chavesDisponiveis
     * @param  array<int,string>  $nomesEpoca  nomes concretos da fruta da epoca no periodo
     */
    private function chaveDoFilho(array $filho, array $chavesDisponiveis, array $nomesEpoca): ?string
    {
        $texto = $this->normalizar(($filho['reference'] ?? '').' '.($filho['name'] ?? ''));

        // 1) Override explicito no mapa de produtos (referencia do filho).
        foreach ($chavesDisponiveis as $chave) {
            $refMapa = $this->normalizar((string) ($this->resolver->resolver($chave)['referencia'] ?? ''));

            if ($refMapa !== '' && $this->normalizar((string) ($filho['reference'] ?? '')) === $refMapa) {
                return $chave;
            }
        }

        // 2) Artigo generico "Fruta da epoca ..." — ganha sempre, mesmo que o
        // nome liste frutos concretos entre parenteses (morango, cereja, ...).
        if (in_array('fruta_epoca', $chavesDisponiveis, true)
            && (str_contains($texto, 'fruta da epoca') || str_contains($texto, 'fruta epoca'))) {
            return 'fruta_epoca';
        }

        // 3) Fruta da epoca: pelo nome concreto do mes (ex. "Pessego").
        if (in_array('fruta_epoca', $chavesDisponiveis, true)) {
            foreach ($nomesEpoca as $nome) {
                if ($nome !== '' && str_contains($texto, $nome)) {
                    return 'fruta_epoca';
                }
            }
        }

        // 4) Por palavras-chave no nome do artigo.
        foreach (self::TOKENS as $chave => $tokens) {
            if (! in_array($chave, $chavesDisponiveis, true)) {
                continue;
            }

            foreach ($tokens as $token) {
                if (str_contains($texto, $token)) {
                    return $chave;
                }
            }
        }

        return null;
    }

    /** Texto do artigo: minusculas, sem acentos, sem underscores. */
    private function normalizar(string $valor): string
    {
        return Str::of($valor)->ascii()->lower()->replace(['_', '-'], ' ')->squish()->toString();
    }

    /**
     * Chave interna (fruta_epoca, frutos_secos, pao_mistura, ...). Mantem o
     * underscore — senao nunca casava com as chaves da tabela TOKENS.
     */
    private function normalizarChave(string $valor): string
    {
        return Str::of($valor)->ascii()->lower()->squish()->replace([' ', '-'], '_')->toString();
    }
}
