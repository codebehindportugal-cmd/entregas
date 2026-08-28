<?php

namespace App\Console\Commands;

use App\Services\MoloniService;
use Illuminate\Console\Command;

class MoloniArtigoCommand extends Command
{
    protected $signature = 'moloni:artigo {referencia : Referencia do artigo (ex. HM5069-0)} {--raw : Mostra o JSON cru do products/getOne}';

    protected $description = 'Mostra um artigo do Moloni e a sua composicao (filhos do artigo composto).';

    public function handle(MoloniService $moloni): int
    {
        $referencia = (string) $this->argument('referencia');

        $artigo = $moloni->produtoPorReferencia($referencia);

        if ($artigo === null) {
            $this->error("Nao existe no Moloni um artigo com a referencia '{$referencia}'.");

            return self::FAILURE;
        }

        $this->info("Artigo: {$artigo['name']} (product_id {$artigo['product_id']}, preco tabela {$artigo['price']})");

        if ($this->option('raw')) {
            $this->line(json_encode($moloni->artigo((int) $artigo['product_id']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $filhos = $moloni->componentesComposto((int) $artigo['product_id']);

        if ($filhos === []) {
            $this->warn('Sem composicao lida pela API. Corre outra vez com --raw para ver a resposta crua do products/getOne.');

            return self::SUCCESS;
        }

        $this->table(
            ['product_id', 'referencia', 'nome', 'qty (composicao)', 'preco tabela'],
            array_map(fn (array $f): array => [$f['product_id'], $f['reference'], $f['name'], $f['qty'], $f['price']], $filhos),
        );

        return self::SUCCESS;
    }
}
