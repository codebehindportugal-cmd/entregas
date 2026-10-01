<?php

namespace App\Services;

use App\Models\Localizacao;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Onde fica uma morada de entrega (latitude, longitude), pelo Nominatim do
 * OpenStreetMap (gratis, sem chave). Cada morada procura-se uma vez e fica
 * guardada na tabela localizacoes; quem pede so le a tabela, a nao ser que
 * diga para procurar as que faltam.
 *
 * O Nominatim pede no maximo um pedido por segundo e um User-Agent proprio.
 */
class Geolocalizador
{
    /** Uma morada que nao se encontrou volta a ser procurada passados estes dias. */
    private const DIAS_ATE_TENTAR_DE_NOVO = 30;

    private static float $ultimoPedido = 0;

    /**
     * @return array{0: float, 1: float}|null
     */
    public function coordenadas(?string $morada, ?string $cp, ?string $localidade = null, bool $procurarSeFaltar = false): ?array
    {
        $morada = self::limpar($morada);
        $cp = self::codigoPostal($cp) ?? self::codigoPostal($morada);

        // A morada com o codigo postal e a localidade, se ainda nao os tiver.
        if ($morada !== '' && $cp !== null && ! str_contains($morada, $cp)) {
            $morada = self::limpar($morada.', '.$cp.' '.$localidade);
        }

        if ($morada === '' && $cp === null) {
            return null;
        }

        $chave = self::chave($morada, $cp);
        $guardada = Localizacao::where('chave', $chave)->first();

        $tentarDeNovo = $guardada !== null
            && $guardada->fonte === 'falhou'
            && $guardada->updated_at?->lt(now()->subDays(self::DIAS_ATE_TENTAR_DE_NOVO));

        if ($guardada !== null && ! $tentarDeNovo) {
            return $guardada->lat !== null ? [$guardada->lat, $guardada->lng] : null;
        }

        if (! $procurarSeFaltar || blank(config('entregas.geocoder_url'))) {
            return null;
        }

        [$coord, $fonte] = $this->procurar($morada, $cp);

        if ($coord === null && $fonte === null) {
            // Erro de rede: nao se guarda, tenta-se na proxima vez.
            return null;
        }

        Localizacao::updateOrCreate(['chave' => $chave], [
            'morada' => $morada ?: null,
            'cp' => $cp,
            'lat' => $coord[0] ?? null,
            'lng' => $coord[1] ?? null,
            'fonte' => $fonte,
        ]);

        return $coord;
    }

    /** As que ja estao guardadas, sem procurar nada. */
    public function guardada(?string $morada, ?string $cp, ?string $localidade = null): ?array
    {
        return $this->coordenadas($morada, $cp, $localidade, false);
    }

    /**
     * @return array{0: array{0: float, 1: float}|null, 1: string|null} [coordenadas, fonte]; fonte null = erro de rede
     */
    private function procurar(string $morada, ?string $cp): array
    {
        try {
            if ($morada !== '' && ($coord = $this->pedir(['q' => $morada.', Portugal'])) !== null) {
                return [$coord, 'morada'];
            }

            if ($cp !== null && ($coord = $this->pedir(['postalcode' => $cp])) !== null) {
                return [$coord, 'codigo_postal'];
            }
        } catch (Throwable $e) {
            Log::warning('Geolocalizador: '.$e->getMessage());

            return [null, null];
        }

        return [null, 'falhou'];
    }

    /** @return array{0: float, 1: float}|null */
    private function pedir(array $parametros): ?array
    {
        $espera = 1.1 - (microtime(true) - self::$ultimoPedido);
        if ($espera > 0 && ! app()->runningUnitTests()) {
            usleep((int) ($espera * 1_000_000));
        }
        self::$ultimoPedido = microtime(true);

        $resposta = Http::withHeaders(['User-Agent' => config('entregas.geocoder_user_agent')])
            ->timeout(15)
            ->get(rtrim((string) config('entregas.geocoder_url'), '/').'/search', $parametros + [
                'format' => 'jsonv2',
                'countrycodes' => 'pt',
                'limit' => 1,
            ])
            ->throw()
            ->json();

        $lat = (float) ($resposta[0]['lat'] ?? 0);
        $lng = (float) ($resposta[0]['lon'] ?? 0);

        // Portugal continental e ilhas; o resto e engano.
        $dentro = ($lat >= 36.8 && $lat <= 42.2 && $lng >= -9.6 && $lng <= -6.1)
            || ($lat >= 32.3 && $lat <= 33.2 && $lng >= -17.4 && $lng <= -16.2)
            || ($lat >= 36.9 && $lat <= 39.8 && $lng >= -31.4 && $lng <= -24.9);

        return $dentro ? [$lat, $lng] : null;
    }

    private static function chave(string $morada, ?string $cp): string
    {
        return sha1(mb_strtolower($morada).'|'.$cp);
    }

    private static function limpar(?string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $texto));
    }

    private static function codigoPostal(?string $texto): ?string
    {
        return preg_match('/\b(\d{4})\s*-\s*(\d{3})\b/', (string) $texto, $m) ? $m[1].'-'.$m[2] : null;
    }
}
