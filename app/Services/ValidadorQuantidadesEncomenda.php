<?php

namespace App\Services;

use App\Models\WooProduct;

/**
 * Converte o que o cliente escreveu para quantidades que o WooCommerce aceita.
 *
 * O Woo so sabe contar linhas inteiras: 4 x "Ameixa 500g". Tudo o que o cliente
 * mande em quilos tem de passar por aqui, e o que nao der para converter sem
 * arriscar para em erro em vez de ser arredondado. Um arredondamento silencioso
 * numa encomenda de fruta e dinheiro perdido ou um cliente sem o que pediu.
 */
class ValidadorQuantidadesEncomenda
{
    private const TOLERANCIA = 0.001;

    /**
     * @param  string|null  $unidade  kg | g | un | emb | null
     * @return array{quantidade_woo: int|null, equivalencia: string|null, avisos: array<int, array<string, mixed>>, erros: array<int, array<string, mixed>>}
     */
    public function validarLinha(WooProduct $produto, float $quantidade, ?string $unidade): array
    {
        $avisos = [];
        $erros = [];
        $unidade = $this->normalizarUnidade($unidade);

        if ($unidade === 'g') {
            $quantidade /= 1000;
            $unidade = 'kg';
        }

        if ($quantidade <= 0) {
            return $this->resposta(null, null, $avisos, [$this->erro('QTD_INVALIDA', 'Quantidade tem de ser maior do que zero.')]);
        }

        $aoPeso = $produto->vendidoAoPeso();

        // Sem unidade: so se assume nos produtos a peca, onde "3 macas" nao tem outra leitura.
        if ($unidade === null) {
            if ($aoPeso) {
                $formato = $produto->descricaoFormato();

                return $this->resposta(null, null, $avisos, [$this->erro(
                    'UNIDADE_EM_FALTA',
                    "{$produto->name} vende-se em {$formato}. \"{$this->numero($quantidade)}\" e quantos quilos ou quantas embalagens?",
                    $this->sugestoesUnidade($produto, $quantidade),
                )]);
            }

            $unidade = 'un';
            $avisos[] = $this->aviso('UNIDADE_ASSUMIDA', "{$produto->name} vende-se a unidade: assumido {$this->numero($quantidade)} un.");
        }

        $quantidadeWoo = null;

        if (! $aoPeso && in_array($unidade, ['un', 'emb'], true)) {
            if (! $this->inteiro($quantidade)) {
                return $this->resposta(null, null, $avisos, [$this->erro(
                    'QTD_NAO_INTEIRA',
                    "{$produto->name} vende-se a unidade e nao da para pedir {$this->numero($quantidade)} un.",
                )]);
            }

            $quantidadeWoo = (int) round($quantidade);
        }

        if (! $aoPeso && $unidade === 'kg') {
            $pesoMedio = $produto->peso_medio_kg !== null ? (float) $produto->peso_medio_kg : null;

            if ($pesoMedio === null || $pesoMedio <= 0) {
                return $this->resposta(null, null, $avisos, [$this->erro(
                    'CONVERSAO_IMPOSSIVEL',
                    "{$produto->name} vende-se a unidade e nao tem peso medio definido — pergunta ao cliente quantas unidades quer.",
                )]);
            }

            $quantidadeWoo = max(1, (int) round($quantidade / $pesoMedio));
            $avisos[] = $this->aviso(
                'CONVERSAO_ESTIMADA',
                "{$this->numero($quantidade)} kg de {$produto->name} = ~{$quantidadeWoo} un (peso medio {$this->numero($pesoMedio)} kg). Confirmar com o cliente.",
            );
        }

        if ($aoPeso && $unidade === 'kg') {
            $formato = (float) $produto->formato_qtd;

            if ($formato <= 0) {
                return $this->resposta(null, null, $avisos, [$this->erro(
                    'FORMATO_POR_DEFINIR',
                    "{$produto->name} esta marcado como vendido ao peso mas nao tem o tamanho da embalagem definido.",
                )]);
            }

            $exato = $quantidade / $formato;

            if (abs($exato - round($exato)) > self::TOLERANCIA) {
                $baixo = max(1, (int) floor($exato));
                $alto = (int) ceil($exato);

                return $this->resposta(null, null, $avisos, [$this->erro(
                    'QTD_NAO_MULTIPLA',
                    "{$produto->name} vende-se em {$produto->descricaoFormato()}: {$this->numero($quantidade)} kg nao da um numero certo de embalagens.",
                    [
                        ['quantidade_woo' => $baixo, 'texto' => "{$baixo} x ".$produto->descricaoFormato()." = {$this->numero($baixo * $formato)} kg"],
                        ['quantidade_woo' => $alto, 'texto' => "{$alto} x ".$produto->descricaoFormato()." = {$this->numero($alto * $formato)} kg"],
                    ],
                )]);
            }

            $quantidadeWoo = (int) round($exato);
        }

        if ($aoPeso && in_array($unidade, ['un', 'emb'], true)) {
            if (! $this->inteiro($quantidade)) {
                return $this->resposta(null, null, $avisos, [$this->erro(
                    'QTD_NAO_INTEIRA',
                    "Nao da para pedir {$this->numero($quantidade)} embalagens de {$produto->name}.",
                )]);
            }

            $quantidadeWoo = (int) round($quantidade);
            $total = $quantidadeWoo * (float) $produto->formato_qtd;
            $avisos[] = $this->aviso(
                'INTERPRETADO_COMO_EMBALAGENS',
                "{$this->numero($quantidade)} de {$produto->name} lido como {$quantidadeWoo} embalagens = {$this->numero($total)} kg.",
            );
        }

        if ($quantidadeWoo === null || $quantidadeWoo < 1) {
            return $this->resposta(null, null, $avisos, [$this->erro('QTD_INVALIDA', "Nao foi possivel apurar a quantidade de {$produto->name}.")]);
        }

        $minimo = max(1, (int) $produto->qtd_min);
        $maximo = $produto->qtd_max !== null ? (int) $produto->qtd_max : null;

        if ($quantidadeWoo < $minimo || ($maximo !== null && $quantidadeWoo > $maximo)) {
            $limite = $maximo !== null ? "entre {$minimo} e {$maximo}" : "no minimo {$minimo}";

            return $this->resposta(null, null, $avisos, [$this->erro(
                'FORA_DOS_LIMITES',
                "{$produto->name} so pode ser encomendado {$limite} (pedido: {$quantidadeWoo}).",
            )]);
        }

        return $this->resposta($quantidadeWoo, $this->equivalencia($produto, $quantidadeWoo), $avisos, $erros);
    }

    /** O texto que vai para o chat confirmar com o cliente. */
    public function equivalencia(WooProduct $produto, int $quantidadeWoo): string
    {
        $texto = "{$quantidadeWoo} x {$produto->name}";

        if ($produto->vendidoAoPeso()) {
            $total = $quantidadeWoo * (float) $produto->formato_qtd;

            return $texto." = {$this->numero($total)} kg";
        }

        $peso = $produto->peso_medio_kg !== null ? (float) $produto->peso_medio_kg : null;

        return $peso !== null
            ? $texto.' (~'.$this->numero($quantidadeWoo * $peso).' kg)'
            : $texto;
    }

    private function normalizarUnidade(?string $unidade): ?string
    {
        if (blank($unidade)) {
            return null;
        }

        return match (mb_strtolower(trim((string) $unidade))) {
            'kg', 'kgs', 'quilo', 'quilos', 'kilo', 'kilos' => 'kg',
            'g', 'gr', 'grama', 'gramas' => 'g',
            'un', 'uni', 'unidade', 'unidades', 'peca', 'pecas' => 'un',
            'emb', 'embalagem', 'embalagens', 'caixa', 'caixas', 'pack' => 'emb',
            default => null,
        };
    }

    private function sugestoesUnidade(WooProduct $produto, float $quantidade): array
    {
        $formato = (float) $produto->formato_qtd;
        $sugestoes = [];

        if ($formato > 0) {
            $emKg = $quantidade / $formato;

            if (abs($emKg - round($emKg)) <= self::TOLERANCIA) {
                $sugestoes[] = [
                    'unidade' => 'kg',
                    'texto' => "{$this->numero($quantidade)} kg = ".(int) round($emKg).' x '.$produto->descricaoFormato(),
                ];
            }
        }

        if ($this->inteiro($quantidade)) {
            $sugestoes[] = [
                'unidade' => 'emb',
                'texto' => $this->numero($quantidade).' x '.$produto->descricaoFormato().' = '.$this->numero($quantidade * $formato).' kg',
            ];
        }

        return $sugestoes;
    }

    private function inteiro(float $valor): bool
    {
        return abs($valor - round($valor)) <= self::TOLERANCIA;
    }

    private function numero(float $valor): string
    {
        $texto = number_format($valor, 3, ',', '');

        return rtrim(rtrim($texto, '0'), ',');
    }

    private function erro(string $codigo, string $mensagem, array $sugestoes = []): array
    {
        return ['codigo' => $codigo, 'mensagem' => $mensagem, 'sugestoes' => $sugestoes];
    }

    private function aviso(string $codigo, string $mensagem): array
    {
        return ['codigo' => $codigo, 'mensagem' => $mensagem];
    }

    private function resposta(?int $quantidadeWoo, ?string $equivalencia, array $avisos, array $erros): array
    {
        return [
            'quantidade_woo' => $quantidadeWoo,
            'equivalencia' => $equivalencia,
            'avisos' => $avisos,
            'erros' => $erros,
        ];
    }
}
