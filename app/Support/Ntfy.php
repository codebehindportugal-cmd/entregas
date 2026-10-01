<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notificacoes push para a app ntfy do telemovel.
 *
 * Copiado do gestao.ateneya.com (29/09/2026) para a gestao da Horta avisar
 * quando a sincronizacao com o Woo ou as renovacoes falham. Mesmo topico.
 *
 * Duas regras que fazem isto ser util em vez de irritante:
 *
 * 1. So se avisa em TRANSICOES — quando algo cai e quando volta. Um site em
 *    baixo verificado de 5 em 5 minutos daria 288 notificacoes por dia; assim
 *    da duas, a da queda e a do regresso.
 *
 * 2. Nunca deita nada abaixo. Se o ntfy nao responder, regista-se no log e a
 *    verificacao segue. Um site em baixo e um problema; nao conseguir avisar
 *    disso nao pode ser um segundo problema.
 */
class Ntfy
{
    /** Algo caiu. */
    public static function emBaixo(string $tipo, string $titulo, string $mensagem, ?string $link = null): void
    {
        self::enviar($tipo, $titulo, $mensagem, prioridade: 'high', tags: 'rotating_light', link: $link);
    }

    /** Algo voltou. */
    public static function recuperou(string $tipo, string $titulo, string $mensagem, ?string $link = null): void
    {
        self::enviar($tipo, $titulo, $mensagem, prioridade: 'default', tags: 'white_check_mark', link: $link);
    }

    /** Correu mal, mas nao e uma queda (um sync ou um backup que falhou). */
    public static function falhou(string $tipo, string $titulo, string $mensagem, ?string $link = null): void
    {
        self::enviar($tipo, $titulo, $mensagem, prioridade: 'high', tags: 'x', link: $link);
    }

    public static function enviar(
        string $tipo,
        string $titulo,
        string $mensagem,
        string $prioridade = 'default',
        string $tags = 'bell',
        ?string $link = null,
    ): bool {
        if (! config('ntfy.enabled') || blank(config('ntfy.topic'))) {
            return false;
        }

        if ($tipo !== 'teste' && ! config("ntfy.avisos.{$tipo}", true)) {
            return false;
        }

        $cabecalhos = [
            'Title'    => $titulo,
            'Priority' => $prioridade,
            'Tags'     => $tags,
        ];

        if ($link) {
            $cabecalhos['Click'] = $link;
        }

        if ($token = config('ntfy.token')) {
            $cabecalhos['Authorization'] = 'Bearer ' . $token;
        }

        try {
            $resposta = Http::withHeaders($cabecalhos)
                ->timeout((int) config('ntfy.timeout'))
                ->withBody($mensagem, 'text/plain')
                ->post(config('ntfy.url') . '/' . config('ntfy.topic'));

            if (! $resposta->successful()) {
                Log::warning('ntfy recusou a notificação', ['status' => $resposta->status(), 'titulo' => $titulo]);
            }

            return $resposta->successful();
        } catch (\Throwable $e) {
            // De propósito engolido: nunca parar uma verificação por causa disto.
            Log::warning('ntfy inacessível: ' . $e->getMessage(), ['titulo' => $titulo]);

            return false;
        }
    }
}
