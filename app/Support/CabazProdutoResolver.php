<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;
use Throwable;

/**
 * Resolve as chaves internas de produtos (usadas nas empresas e nos cabazes)
 * para a designacao real que deve aparecer na fatura Moloni.
 *
 * Serve sobretudo para produtos "genericos" que mudam ao longo do ano:
 *  - "fruta_epoca" (Fruta da epoca) -> o fruto concreto do periodo (ex.: Pessego)
 *  - "kiwi"                          -> o calibre/nome real (ex.: Kiwi calibre 39)
 *
 * Os mapeamentos ficam guardados num Setting (key = faturacao_mapa_produtos)
 * em JSON, com um bloco "default" e blocos opcionais por mes (YYYY-MM):
 * {
 *   "default": {
 *     "fruta_epoca": { "nome": "Fruta da epoca", "referencia": "FRUTA-EPOCA" },
 *     "kiwi":        { "nome": "Kiwi", "referencia": "KIWI" }
 *   },
 *   "2026-07": {
 *     "fruta_epoca": { "nome": "Melao", "referencia": "FRUTA-EPOCA" }
 *   }
 * }
 */
class CabazProdutoResolver
{
    public const SETTING_KEY = 'faturacao_mapa_produtos';

    /** Chaves que tipicamente precisam de mapeamento para um produto concreto. */
    public const CHAVES_VARIAVEIS = ['fruta_epoca', 'kiwi'];

    /** Rotulos por defeito das chaves internas das empresas (Corporate). */
    private const LABELS = [
        'banana' => 'Banana',
        'maca' => 'Maca',
        'pera' => 'Pera',
        'laranja' => 'Laranja',
        'kiwi' => 'Kiwi',
        'uvas' => 'Uvas',
        'fruta_epoca' => 'Fruta da epoca',
        'frutos_secos' => 'Frutos secos',
        'mirtilos' => 'Mirtilos',
        'framboesas' => 'Framboesas',
        'amoras' => 'Amoras',
        'morangos' => 'Morangos',
        'pao_mistura' => 'Pao de mistura',
        'pao_forma' => 'Pao de forma',
        'croissant' => 'Croissant',
        'bolo' => 'Bolo',
    ];

    private ?array $mapa = null;

    /**
     * Resolve uma chave/nome de produto para a linha de fatura.
     *
     * @return array{referencia:string,nome:string,preco:?float}
     */
    public function resolver(string $chaveOuNome, ?string $periodo = null): array
    {
        $chave = $this->normalizarChave($chaveOuNome);
        $override = $this->mapeamento($chave, $periodo);

        $nome = $override['nome'] ?? $this->labelPadrao($chaveOuNome);
        $referencia = $override['referencia'] ?? $this->referenciaPadrao($chaveOuNome);
        $preco = isset($override['preco']) && is_numeric($override['preco']) ? (float) $override['preco'] : null;

        return [
            'referencia' => Str::upper(trim($referencia)),
            'nome' => trim($nome),
            'preco' => $preco,
        ];
    }

    /**
     * Nomes concretos da "fruta da epoca" para um periodo (pode ser varios).
     * Le do mapeamento (Setting) a chave fruta_epoca: aceita `nomes` (lista) ou
     * `nome` (texto, podendo ter varios separados por virgula / ponto-e-virgula).
     *
     * @return array<int,string>
     */
    public function frutasEpoca(?string $periodo = null): array
    {
        $override = $this->mapeamento('fruta_epoca', $periodo) ?? [];

        $nomes = [];

        if (isset($override['nomes']) && is_array($override['nomes'])) {
            $nomes = $override['nomes'];
        } elseif (isset($override['nome']) && is_string($override['nome'])) {
            $nomes = preg_split('/\s*[,;\/]\s*/', $override['nome']) ?: [];
        }

        return array_values(array_filter(
            array_map(fn ($n) => trim((string) $n), $nomes),
            fn (string $n): bool => $n !== '',
        ));
    }

    /**
     * Indica se a chave e uma das que costuma precisar de mapeamento manual.
     */
    public function precisaMapeamento(string $chaveOuNome): bool
    {
        return in_array($this->normalizarChave($chaveOuNome), self::CHAVES_VARIAVEIS, true);
    }

    public function labelPadrao(string $chaveOuNome): string
    {
        $chave = $this->normalizarChave($chaveOuNome);

        if (isset(self::LABELS[$chave])) {
            return self::LABELS[$chave];
        }

        // Nomes livres (ex.: itens de ListaCabaz) sao usados tal como vem.
        return trim($chaveOuNome) !== '' ? trim($chaveOuNome) : 'Produto';
    }

    private function referenciaPadrao(string $chaveOuNome): string
    {
        $chave = $this->normalizarChave($chaveOuNome);

        if (isset(self::LABELS[$chave])) {
            return Str::upper(str_replace('_', '-', $chave));
        }

        return Str::upper(Str::slug($chaveOuNome, '-')) ?: 'PRODUTO';
    }

    /**
     * @return array{nome?:string,referencia?:string}|null
     */
    private function mapeamento(string $chave, ?string $periodo): ?array
    {
        $mapa = $this->mapa();

        $periodoMap = $periodo !== null ? ($mapa[$periodo][$chave] ?? null) : null;
        $defaultMap = $mapa['default'][$chave] ?? null;

        $resultado = array_merge(
            is_array($defaultMap) ? $defaultMap : [],
            is_array($periodoMap) ? $periodoMap : [],
        );

        return $resultado !== [] ? $resultado : null;
    }

    private function mapa(): array
    {
        if ($this->mapa !== null) {
            return $this->mapa;
        }

        try {
            $raw = Setting::query()->where('key', self::SETTING_KEY)->value('value');
            $decoded = filled($raw) ? json_decode((string) $raw, true) : null;
            $this->mapa = is_array($decoded) ? $decoded : [];
        } catch (Throwable) {
            // Tabela settings ainda nao migrada ou indisponivel: usa mapa vazio.
            $this->mapa = [];
        }

        return $this->mapa;
    }

    private function normalizarChave(string $valor): string
    {
        $chave = Str::of($valor)->lower()->replace(' ', '_')->replace('-', '_')->toString();

        // O kiwi foi fundido na fruta da epoca: qualquer referencia a kiwi
        // e tratada como fruta_epoca (soma-se a fruta da epoca ao faturar).
        if ($chave === 'kiwi') {
            return 'fruta_epoca';
        }

        return $chave;
    }
}
