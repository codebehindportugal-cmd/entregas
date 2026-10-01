<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Minutos de carro entre todos os pontos de uma volta, pelas estradas
 * (OSRM / OpenStreetMap, gratis e sem chave). Um so pedido por volta, guardado
 * em cache: as voltas repetem-se quase sempre iguais.
 *
 * Se o servico nao responder devolve null e o organizador usa a estimativa em
 * linha reta.
 */
class TemposDeEstrada
{
    /** O servidor publico do OSRM aceita ate 100 pontos por pedido. */
    private const MAXIMO_PONTOS = 100;

    /**
     * @param  array<int, array{0: float, 1: float}>  $pontos  [lat, lng]
     * @return array<int, array<int, int>>|null minutos de $pontos[i] a $pontos[j]
     */
    public function matriz(array $pontos): ?array
    {
        $pontos = array_values($pontos);
        $url = config('entregas.osrm_url');

        if (blank($url) || count($pontos) < 2 || count($pontos) > self::MAXIMO_PONTOS) {
            return null;
        }

        $coordenadas = implode(';', array_map(
            fn (array $p): string => sprintf('%.5f,%.5f', $p[1], $p[0]),
            $pontos
        ));
        $chave = 'osrm:'.sha1($coordenadas);

        if (($guardada = Cache::get($chave)) !== null) {
            return $guardada;
        }

        try {
            $resposta = Http::timeout(20)
                ->get(rtrim((string) $url, '/').'/table/v1/driving/'.$coordenadas, ['annotations' => 'duration'])
                ->throw()
                ->json();
        } catch (Throwable $e) {
            Log::warning('TemposDeEstrada: '.$e->getMessage());

            return null;
        }

        if (($resposta['code'] ?? null) !== 'Ok' || ! is_array($resposta['durations'] ?? null)) {
            return null;
        }

        // A carrinha anda mais devagar que um carro e ainda e preciso
        // estacionar: o tempo do OSRM vezes um fator, mais uns minutos.
        $fator = (float) config('entregas.fator_tempo_estrada', 1.15);
        $estacionar = (int) config('entregas.minutos_estacionar', 3);

        $minutos = [];
        foreach ($resposta['durations'] as $i => $linha) {
            foreach ($linha as $j => $segundos) {
                $minutos[$i][$j] = match (true) {
                    $i === $j => 0,
                    $segundos === null => null,
                    // Mesmo predio ou porta ao lado: nao se volta a estacionar.
                    $segundos < 60 => 1,
                    default => (int) round($segundos / 60 * $fator) + $estacionar,
                };
            }
        }

        Cache::put($chave, $minutos, now()->addDays(30));

        return $minutos;
    }
}
