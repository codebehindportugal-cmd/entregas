<?php

namespace App\Console\Commands;

use App\Services\MoloniService;
use Illuminate\Console\Command;

class MoloniSetup extends Command
{
    protected $signature = 'moloni:setup';

    protected $description = 'Lista empresas, series, IVA e metodos de pagamento do Moloni com os IDs para o .env';

    public function handle(MoloniService $moloni): int
    {
        $this->info('== Moloni :: configuracao ==');

        // 1) Empresas (nao depende de company_id)
        $contas = $moloni->listarContas();

        if ($contas === []) {
            $this->error('Nao foi possivel obter as empresas. Confirma no .env: MOLONI_DEVELOPER_ID, MOLONI_CLIENT_SECRET, MOLONI_USERNAME, MOLONI_PASSWORD.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('EMPRESAS (usa o company_id da Horta da Maria em MOLONI_COMPANY_ID):');
        $this->table(['company_id', 'Nome'], collect($contas)->map(fn (array $c): array => [
            $c['company_id'] ?? '?',
            $c['name'] ?? ($c['title'] ?? '?'),
        ])->all());

        $companyId = config('moloni.company_id') ?: env('MOLONI_COMPANY_ID');

        if (blank($companyId)) {
            $this->warn('Define primeiro MOLONI_COMPANY_ID no .env (da tabela acima) e volta a correr para veres series, IVA e pagamentos.');

            return self::SUCCESS;
        }

        // 2) Series de documentos
        $series = $moloni->listarSeries();
        $this->newLine();
        $this->line('SERIES (document_set_id) -> MOLONI_DOCUMENT_SET_ID_FATURA e MOLONI_DOCUMENT_SET_ID_FATURA_RECIBO:');
        $this->table(['document_set_id', 'Nome', 'Por defeito'], collect($series)->map(fn (array $s): array => [
            $s['document_set_id'] ?? '?',
            $s['name'] ?? '?',
            ($s['is_default'] ?? 0) ? 'Sim' : '',
        ])->all());

        // 3) IVA
        $taxas = $moloni->listarTaxas();
        $this->newLine();
        $this->line('IVA (tax_id) -> MOLONI_TAX_ID (a taxa de 6% e a reduzida do continente):');
        $this->table(['tax_id', 'Nome', 'Valor %'], collect($taxas)->map(fn (array $t): array => [
            $t['tax_id'] ?? '?',
            $t['name'] ?? '?',
            $t['value'] ?? '?',
        ])->all());

        // 4) Metodos de pagamento
        $metodos = $moloni->listarMetodosPagamento();
        $this->newLine();
        $this->line('METODOS DE PAGAMENTO (payment_method_id) -> MOLONI_PAYMENT_METHOD_ID (obrigatorio p/ Fatura-Recibo):');
        $this->table(['payment_method_id', 'Nome'], collect($metodos)->map(fn (array $m): array => [
            $m['payment_method_id'] ?? '?',
            $m['name'] ?? '?',
        ])->all());

        $this->newLine();
        $this->info('Copia os IDs para o .env e corre `php artisan config:clear`.');

        return self::SUCCESS;
    }
}
