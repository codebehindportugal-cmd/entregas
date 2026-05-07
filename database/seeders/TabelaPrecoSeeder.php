<?php

namespace Database\Seeders;

use App\Models\TabelaPreco;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TabelaPrecoSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('promt vscodeprecoscabaz.md');
        $items = file_exists($path) ? $this->itemsFromPrompt((string) file_get_contents($path)) : [];

        $tabela = TabelaPreco::updateOrCreate(
            [
                'fornecedor' => 'Sentido da Fruta',
                'valida_de' => '2026-04-07',
            ],
            [
                'descricao' => 'Tabela Abril 2026',
                'valida_ate' => '2026-04-10',
                'ativa' => true,
            ]
        );

        if ($items !== []) {
            $tabela->itens()->delete();
            $tabela->itens()->createMany($items);
        }
    }

    private function itemsFromPrompt(string $content): array
    {
        $categoria = null;
        $ordem = 0;
        $items = [];

        foreach (preg_split('/\R/', $content) ?: [] as $line) {
            $line = trim($line);

            if (preg_match('/^\*\*Categoria:\s*(.+)\*\*$/u', $line, $matches)) {
                $categoria = $this->categoria((string) $matches[1]);
                $ordem = 0;

                continue;
            }

            if ($categoria === null || ! str_starts_with($line, '|') || str_contains($line, '---') || str_contains($line, 'Produto |')) {
                continue;
            }

            $columns = collect(explode('|', trim($line, '|')))
                ->map(fn (string $value): string => trim($value))
                ->values();

            if ($columns->count() < 4) {
                continue;
            }

            $produto = $columns->get(0);
            $origem = $columns->get(1);
            $hasCalibre = $columns->count() >= 5;
            $calibre = $hasCalibre ? $columns->get(2) : null;
            $preco = $this->decimal($columns->get($hasCalibre ? 3 : 2));
            $precoIva = $this->decimal($columns->get($hasCalibre ? 4 : 3));

            if ($produto === '' || $preco === null || $precoIva === null) {
                continue;
            }

            $items[] = [
                'categoria' => $categoria,
                'produto' => $produto,
                'origem' => $origem ?: null,
                'calibre' => $calibre ?: null,
                'preco_kg' => $preco,
                'preco_kg_iva' => $precoIva,
                'unidade' => Str::of($produto)->lower()->contains(['molho', 'covete', 'embal']) ? 'un' : 'kg',
                'notas' => null,
                'ordem' => $ordem++,
            ];
        }

        return $items;
    }

    private function categoria(string $categoria): string
    {
        $normalized = Str::of($categoria)->ascii()->toString();
        $normalized = str_replace(['Frutas ExA3ticas', 'Frutas Exoticas'], 'Frutas Exoticas', $normalized);
        $normalized = str_replace(['MaASS', 'Macas', 'MaAs'], 'Macas', $normalized);
        $normalized = str_replace(['PAras', 'Peras'], 'Peras', $normalized);

        return match (true) {
            str_contains($normalized, 'Ex') => 'Frutas Exoticas',
            str_contains($normalized, 'Citr') => 'Citrinos',
            str_contains($normalized, 'Mac') => 'Macas',
            str_contains($normalized, 'Per') => 'Peras',
            str_contains($normalized, 'Leg') => 'Legumes',
            str_contains($normalized, 'Cog') => 'Cogumelos',
            str_contains($normalized, 'Bat') => 'Batatas',
            str_contains($normalized, 'Coz') => 'Cozidos e Embalados',
            str_contains($normalized, 'Sec') => 'Secos',
            default => 'Frutas',
        };
    }

    private function decimal(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (float) str_replace(',', '.', preg_replace('/[^\d,.]/', '', $value));
    }
}
