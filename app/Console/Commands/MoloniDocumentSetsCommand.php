<?php

namespace App\Console\Commands;

use App\Services\MoloniService;
use Illuminate\Console\Command;

class MoloniDocumentSetsCommand extends Command
{
    protected $signature = 'moloni:document-sets';

    protected $description = 'Lista as series de documentos do Moloni (document_set_id + nome).';

    public function handle(MoloniService $moloni): int
    {
        $sets = $moloni->documentSets();

        if ($sets === []) {
            $this->error('Nao foi possivel obter as series do Moloni. Verifica as credenciais no .env e a ligacao.');

            return self::FAILURE;
        }

        $this->table(
            ['document_set_id', 'nome'],
            array_map(fn (array $set): array => [$set['document_set_id'], $set['name']], $sets),
        );

        $this->line('');
        $this->info('Copia o ID da serie da Guia de Transporte para MOLONI_DOCUMENT_SET_ID_GUIA no .env.');

        return self::SUCCESS;
    }
}
