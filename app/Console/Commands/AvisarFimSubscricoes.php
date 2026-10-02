<?php

namespace App\Console\Commands;

use App\Models\WooOrder;
use App\Support\Ntfy;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Avisa no telemovel (ntfy, o mesmo topico do agro e do gestao.ateneya.com)
 * quando uma subscricao chega ao fim do ciclo de 4 entregas. Corre depois de
 * subscricoes:renovar, para o aviso ja dizer se a renovacao foi criada.
 *
 * Um aviso por dia com todas as que acabaram; cada fim de ciclo so se avisa
 * uma vez (fica em fim_ciclo_avisado).
 */
class AvisarFimSubscricoes extends Command
{
    protected $signature = 'subscricoes:avisar-fim {--dry-run : So mostra o que avisaria}';

    protected $description = 'Envia para o ntfy as subscricoes que terminaram o ciclo';

    /** Quantas linhas cabem numa notificacao antes de resumir. */
    private const MAXIMO_LINHAS = 8;

    public function handle(): int
    {
        $terminadas = WooOrder::query()
            ->where(function ($query): void {
                $query->where('source_type', 'subscription')
                    ->orWhereIn('status', ['subscricao', 'wc-subscricao', 'active']);
            })
            ->get()
            ->filter(fn (WooOrder $order): bool => $order->precisaDeAvisoDeFim())
            ->sortBy(fn (WooOrder $order): string => $order->fimDoUltimoCiclo().' '.$order->billing_name)
            ->values();

        if ($terminadas->isEmpty()) {
            $this->info('Nenhuma subscricao terminou.');

            return self::SUCCESS;
        }

        $linhas = $terminadas->map(fn (WooOrder $order): string => $this->linha($order));
        $semRenovacao = $terminadas->filter(fn (WooOrder $order): bool => $order->renovada_em === null)->count();

        $titulo = $terminadas->count() === 1
            ? 'Horta: 1 subscricao terminou'
            : 'Horta: '.$terminadas->count().' subscricoes terminaram';

        $mensagem = $linhas->take(self::MAXIMO_LINHAS)->implode("\n");

        if ($linhas->count() > self::MAXIMO_LINHAS) {
            $mensagem .= "\n... e mais ".($linhas->count() - self::MAXIMO_LINHAS);
        }

        if ($this->option('dry-run')) {
            $this->line($titulo);
            $this->line($mensagem);

            return self::SUCCESS;
        }

        $enviado = Ntfy::enviar(
            'fim_subscricoes',
            $titulo,
            $mensagem,
            // Se ha alguma sem renovacao e preciso falar com o cliente.
            prioridade: $semRenovacao > 0 ? 'high' : 'default',
            tags: 'package',
            link: rtrim((string) config('app.url'), '/').'/encomendas',
        );

        // So se marca como avisado se o aviso saiu; senao tenta-se amanha
        // (dentro da janela de dias).
        if ($enviado) {
            $terminadas->each(fn (WooOrder $order) => $order->marcarFimAvisado());
            $this->info("Aviso enviado: {$terminadas->count()} subscricao(oes).");
        } else {
            $this->warn('O ntfy nao recebeu o aviso (desligado ou sem resposta).');
        }

        return self::SUCCESS;
    }

    private function linha(WooOrder $order): string
    {
        $nome = $order->billing_name ?: 'sem nome';
        $fim = Carbon::parse($order->fimDoUltimoCiclo())->format('d/m');
        $estado = match (true) {
            $order->renovada_em !== null => 'renovada'.($order->renovacaoWooOrder()?->woo_id ? ' (#'.$order->renovacaoWooOrder()->woo_id.')' : ''),
            (bool) $order->renovacao_automatica => 'renovacao ainda por criar',
            default => 'sem renovacao automatica - falar com o cliente',
        };

        return "#{$order->woo_id} {$nome}, ultima entrega {$fim}: {$estado}";
    }
}
