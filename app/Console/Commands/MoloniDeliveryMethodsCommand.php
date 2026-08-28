<?php

namespace App\Console\Commands;

use App\Services\MoloniService;
use Illuminate\Console\Command;

class MoloniDeliveryMethodsCommand extends Command
{
    protected $signature = 'moloni:delivery-methods';

    protected $description = 'Lista os metodos de expedicao do Moloni (delivery_method_id + nome).';

    public function handle(MoloniService $moloni): int
    {
        $metodos = $moloni->deliveryMethods();

        if ($metodos === []) {
            $this->error('Nao foi possivel obter os metodos de expedicao do Moloni.');

            return self::FAILURE;
        }

        $this->table(
            ['delivery_method_id', 'nome'],
            array_map(fn (array $m): array => [$m['delivery_method_id'], $m['name']], $metodos),
        );

        $this->line('');
        $this->info("Copia o ID de 'Nossa Viatura' para MOLONI_GUIA_DELIVERY_METHOD_ID no .env.");

        return self::SUCCESS;
    }
}
