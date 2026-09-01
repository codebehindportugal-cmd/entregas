<?php

namespace App\Console\Commands;

use App\Models\WooOrder;
use App\Services\RenovacaoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RenovarSubscricoes extends Command
{
    protected $signature = 'subscricoes:renovar {--dry-run : So mostra o que faria}';

    protected $description = 'Cria as encomendas de renovacao das subscricoes auto-renovaveis que chegaram ao fim do ciclo';

    public function handle(RenovacaoService $renovacao): int
    {
        $candidatas = WooOrder::query()
            ->where('renovacao_automatica', true)
            ->whereNull('renovada_em')
            ->where(function ($query): void {
                $query->where('source_type', 'subscription')
                    ->orWhereIn('status', ['subscricao', 'wc-subscricao', 'active']);
            })
            ->get()
            // A janela evita que subscricoes antigas gerem renovacoes de repente.
            ->filter(fn (WooOrder $order): bool => $order->precisaDeRenovacao(dentroDaJanela: true));

        if ($candidatas->isEmpty()) {
            $this->info('Nao ha subscricoes para renovar.');

            return self::SUCCESS;
        }

        foreach ($candidatas as $subscricao) {
            $etiqueta = "#{$subscricao->woo_id} ".($subscricao->billing_name ?: 'sem nome');

            if ($this->option('dry-run')) {
                $this->line("[dry-run] Renovaria {$etiqueta}");

                continue;
            }

            try {
                $nova = $renovacao->renovar($subscricao);
                $this->info("Renovacao criada para {$etiqueta}: encomenda #{$nova->woo_id}.");
            } catch (Throwable $exception) {
                Log::error('Falhou a renovacao automatica da subscricao', [
                    'woo_order_id' => $subscricao->id,
                    'woo_id' => $subscricao->woo_id,
                    'erro' => $exception->getMessage(),
                ]);

                $this->error("Falhou a renovacao de {$etiqueta}: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
