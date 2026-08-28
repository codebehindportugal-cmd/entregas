<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\CabazProdutoResolver;
use Illuminate\Console\Command;

class MoloniFrutaEpocaCommand extends Command
{
    protected $signature = 'moloni:fruta-epoca
        {nome : Nome da fruta da epoca (ex.: Nectarina). Podem ser varias separadas por virgula}
        {--periodo= : Periodo YYYY-MM (por defeito o mes atual). Use "default" para todos os meses sem override}
        {--referencia= : Referencia do produto no Moloni (opcional)}';

    protected $description = 'Define o nome da fruta da epoca de um periodo (mapeamento faturacao_mapa_produtos).';

    public function handle(): int
    {
        $nome = trim((string) $this->argument('nome'));

        if ($nome === '') {
            $this->error('Indica o nome da fruta.');

            return self::FAILURE;
        }

        $periodo = (string) ($this->option('periodo') ?: now()->format('Y-m'));
        $referencia = $this->option('referencia');

        $key = CabazProdutoResolver::SETTING_KEY;
        $raw = Setting::query()->where('key', $key)->value('value');
        $mapa = filled($raw) ? (json_decode((string) $raw, true) ?: []) : [];

        if (! is_array($mapa)) {
            $mapa = [];
        }

        $mapa[$periodo]['fruta_epoca']['nome'] = $nome;

        if (filled($referencia)) {
            $mapa[$periodo]['fruta_epoca']['referencia'] = $referencia;
        }

        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => json_encode($mapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );

        $this->info("Fruta da epoca para '{$periodo}' definida como: {$nome}");
        $this->line('A guia e a fatura passam a mostrar este nome em vez de "Fruta da epoca".');

        return self::SUCCESS;
    }
}
