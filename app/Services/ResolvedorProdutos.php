<?php

namespace App\Services;

use App\Models\WooProduct;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Do "2 kg de ameixas" para um produto do site.
 *
 * A regra que interessa: quando ha duvida, nao se escolhe. Se dois produtos
 * parecem igualmente provaveis devolvem-se os dois e quem chama tem de perguntar
 * ao cliente — e preferivel uma pergunta a mais do que uma encomenda com o
 * produto errado.
 */
class ResolvedorProdutos
{
    /** Abaixo disto nem se considera parecido. */
    private const LIMIAR = 82.0;

    private ?Collection $catalogo = null;

    /**
     * @return array{produto: WooProduct|null, candidatos: Collection<int, array{produto: WooProduct, score: float}>, confianca: string}
     */
    public function resolver(string $texto, bool $apenasDisponiveis = true): array
    {
        $procurado = $this->normalizar($texto);

        if ($procurado === '') {
            return $this->resultado(null, collect(), 'nenhuma');
        }

        $produtos = $this->catalogo($apenasDisponiveis);

        // 1) Nome/sku/alias exatamente igual.
        $exatos = $produtos->filter(function (WooProduct $produto) use ($procurado): bool {
            return collect($produto->nomesConhecidos())
                ->map(fn (string $nome): string => $this->normalizar($nome))
                ->contains($procurado);
        });

        if ($exatos->count() === 1) {
            return $this->resultado($exatos->first(), $this->comScore($exatos, 100.0), 'exata');
        }

        if ($exatos->count() > 1) {
            return $this->resultado(null, $this->comScore($exatos, 100.0), 'ambigua');
        }

        // 2) Parecido: o melhor de todos os nomes conhecidos de cada produto.
        $pontuados = $produtos
            ->map(fn (WooProduct $produto): array => [
                'produto' => $produto,
                'score' => $this->melhorScore($produto, $procurado),
            ])
            ->filter(fn (array $linha): bool => $linha['score'] >= self::LIMIAR)
            ->sortByDesc('score')
            ->values();

        if ($pontuados->isEmpty()) {
            return $this->resultado(null, collect(), 'nenhuma');
        }

        $melhor = $pontuados->first();
        $segundo = $pontuados->get(1);

        // Empate tecnico (menos de 5 pontos entre o primeiro e o segundo): ambiguo.
        if ($segundo !== null && ($melhor['score'] - $segundo['score']) < 5.0) {
            return $this->resultado(null, $pontuados->take(5), 'ambigua');
        }

        return $this->resultado($melhor['produto'], $pontuados->take(5), 'provavel');
    }

    public function porId(int $id): ?WooProduct
    {
        return WooProduct::find($id);
    }

    /** @return Collection<int, WooProduct> */
    public function catalogo(bool $apenasDisponiveis = true): Collection
    {
        $this->catalogo ??= WooProduct::query()->orderBy('name')->get();

        return $apenasDisponiveis
            ? $this->catalogo->filter(fn (WooProduct $p): bool => $p->compraAtiva())->values()
            : $this->catalogo;
    }

    private function melhorScore(WooProduct $produto, string $procurado): float
    {
        return collect($produto->nomesConhecidos())
            ->map(fn (string $nome): float => $this->score($this->normalizar($nome), $procurado))
            ->max() ?? 0.0;
    }

    private function score(string $nome, string $procurado): float
    {
        if ($nome === '' || $procurado === '') {
            return 0.0;
        }

        if ($nome === $procurado) {
            return 100.0;
        }

        // "ameixa" dentro de "ameixa rainha claudia 500g" conta como forte.
        if (Str::contains($nome, $procurado) || Str::contains($procurado, $nome)) {
            return 92.0;
        }

        similar_text($nome, $procurado, $percentagem);

        return round($percentagem, 1);
    }

    /**
     * @param  Collection<int, WooProduct>  $produtos
     * @return Collection<int, array{produto: WooProduct, score: float}>
     */
    private function comScore(Collection $produtos, float $score): Collection
    {
        return $produtos->map(fn (WooProduct $produto): array => [
            'produto' => $produto,
            'score' => $score,
        ])->values();
    }

    /**
     * @param  Collection<int, array{produto: WooProduct, score: float}>  $candidatos
     */
    private function resultado(?WooProduct $produto, Collection $candidatos, string $confianca): array
    {
        return [
            'produto' => $produto,
            'candidatos' => $candidatos,
            'confianca' => $confianca,
        ];
    }

    /** Sem acentos, sem plurais simples, sem artigos nem pontuacao. */
    private function normalizar(?string $texto): string
    {
        $texto = Str::lower(Str::ascii((string) $texto));
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '';
        $palavras = collect(explode(' ', trim($texto)))
            ->filter(fn (string $p): bool => $p !== '' && ! in_array($p, ['de', 'da', 'do', 'das', 'dos', 'a', 'o', 'as', 'os', 'e'], true))
            ->map(fn (string $p): string => Str::endsWith($p, 's') && Str::length($p) > 3 ? Str::substr($p, 0, -1) : $p);

        return $palavras->implode(' ');
    }
}
