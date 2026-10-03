<?php

namespace App\Http\Controllers;

use App\Models\PedidoRecebido;
use App\Services\PedidosRecebidosService;
use App\Support\PedidosSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Webhook da WhatsApp Cloud API (Meta).
 *
 * Cada mensagem que chega ao numero da Horta da Maria entra na caixa de pedidos
 * como "novo". As mensagens seguidas do mesmo cliente (ate 12 horas) juntam-se
 * no mesmo pedido, porque os clientes costumam mandar a encomenda aos bocados.
 * A interpretacao fica para o trabalho do fim do dia do Claude.
 *
 * As chaves (verify token e app secret) estao em Clientes -> Definicoes de pedidos.
 */
class WhatsAppWebhookController extends Controller
{
    private const HORAS_A_JUNTAR = 12;

    /** A Meta chama isto uma vez, quando se configura o webhook. */
    public function verificar(Request $request): Response
    {
        $token = PedidosSettings::get('whatsapp_verify_token');

        if ($request->query('hub_mode') === 'subscribe'
            && filled($token)
            && hash_equals((string) $token, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
        }

        return response('Verificacao recusada', 403);
    }

    public function receber(Request $request, PedidosRecebidosService $service): Response
    {
        if (! PedidosSettings::ligado('whatsapp_ativo')) {
            // Responde 200 na mesma para a Meta nao ficar a repetir.
            return response('desligado', 200);
        }

        $segredo = PedidosSettings::get('whatsapp_app_secret');

        if (blank($segredo)) {
            Log::warning('Webhook do WhatsApp recebido sem app secret definido nas definicoes de pedidos.');

            return response('Sem app secret', 403);
        }

        $esperada = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $segredo);

        if (! hash_equals($esperada, (string) $request->header('X-Hub-Signature-256'))) {
            return response('Assinatura invalida', 403);
        }

        $novos = 0;

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $valor = (array) ($change['value'] ?? []);
                $nomes = collect((array) ($valor['contacts'] ?? []))
                    ->mapWithKeys(fn ($c): array => [($c['wa_id'] ?? '') => $c['profile']['name'] ?? null]);

                foreach ((array) ($valor['messages'] ?? []) as $mensagem) {
                    $novos += $this->guardarMensagem((array) $mensagem, $nomes->all()) ? 1 : 0;
                }
            }
        }

        $service->avisar($novos);

        return response('ok', 200);
    }

    /** @return bool true quando abriu um pedido novo na caixa */
    private function guardarMensagem(array $mensagem, array $nomes): bool
    {
        $id = (string) ($mensagem['id'] ?? '');
        $de = (string) ($mensagem['from'] ?? '');

        if ($id === '' || $de === '' || ! Cache::add('whatsapp-msg:'.$id, true, now()->addDays(7))) {
            return false; // sem id, ou a Meta a repetir uma mensagem que ja entrou
        }

        $texto = $this->textoDaMensagem($mensagem);
        $quando = isset($mensagem['timestamp']) ? Carbon::createFromTimestamp((int) $mensagem['timestamp']) : now();
        $remetente = trim(($nomes[$de] ?? '').' +'.$de);

        $aberto = PedidoRecebido::query()
            ->where('canal', 'whatsapp')
            ->where('remetente', $remetente)
            ->where('estado', 'novo')
            ->where('recebido_em', '>=', now()->subHours(self::HORAS_A_JUNTAR))
            ->latest('id')
            ->first();

        if ($aberto !== null) {
            $aberto->forceFill([
                'texto_original' => $aberto->texto_original."\n".$this->linha($quando, $texto),
            ])->save();

            return false;
        }

        PedidoRecebido::create([
            'canal' => 'whatsapp',
            'origem_id' => $id,
            'remetente' => $remetente,
            'recebido_em' => $quando,
            'texto_original' => $this->linha($quando, $texto),
            'estado' => 'novo',
        ]);

        return true;
    }

    private function linha(Carbon $quando, string $texto): string
    {
        return '['.$quando->timezone(config('app.timezone'))->format('d/m H:i').'] '.$texto;
    }

    private function textoDaMensagem(array $mensagem): string
    {
        return match ($mensagem['type'] ?? 'text') {
            'text' => (string) ($mensagem['text']['body'] ?? ''),
            'image' => '[imagem'.(filled($mensagem['image']['caption'] ?? null) ? ': '.$mensagem['image']['caption'] : '').' — ver no WhatsApp]',
            'audio' => '[mensagem de voz — ouvir no WhatsApp]',
            'document' => '[documento '.($mensagem['document']['filename'] ?? '').' — ver no WhatsApp]',
            'location' => '[localizacao: '.($mensagem['location']['latitude'] ?? '?').', '.($mensagem['location']['longitude'] ?? '?').']',
            default => '['.($mensagem['type'] ?? 'mensagem').' — ver no WhatsApp]',
        };
    }
}
