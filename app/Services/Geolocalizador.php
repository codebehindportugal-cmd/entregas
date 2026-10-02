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

        // Uma morada guardada que caiu longe do codigo postal (o Nominatim
        // encontrou uma rua com o mesmo nome noutra terra) nao serve: volta-se
        // a procurar.
        $guardadaLonge = $guardada !== null
            && $guardada->lat !== null
            && self::longeDoCodigoPostal([(float) $guardada->lat, (float) $guardada->lng], $cp);

        if ($guardada !== null && ! $tentarDeNovo && ! $guardadaLonge) {
            return $guardada->lat !== null ? [$guardada->lat, $guardada->lng] : null;
        }

        if ($guardadaLonge && ! $procurarSeFaltar) {
            return null;
        }

        if (! $procurarSeFaltar || blank(config('entregas.geocoder_url'))) {
            return null;
        }

        [$coord, $fonte] = $this->procurar($morada, $cp);

        if ($coord === null && $fonte === null) {
            // Erro de rede: nao se guarda, tenta-se na proxima vez.
            return null;
        }

        // Um so INSERT ... ON DUPLICATE KEY UPDATE: com updateOrCreate, dois
        // pedidos a procurar a mesma morada ao mesmo tempo (organizar a volta
        // enquanto corre o entregas:geolocalizar) davam erro de chave repetida.
        Localizacao::upsert([[
            'chave' => $chave,
            'morada' => $morada ?: null,
            'cp' => $cp,
            'lat' => $coord[0] ?? null,
            'lng' => $coord[1] ?? null,
            'fonte' => $fonte,
        ]], ['chave'], ['morada', 'cp', 'lat', 'lng', 'fonte', 'updated_at']);

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
            // A morada como esta escrita; depois sem o que vem antes da rua
            // (nome do edificio, "Z. I.", ...), que muitas vezes faz o
            // Nominatim nao encontrar nada.
            foreach (self::variantes($morada, $cp === null) as $texto) {
                $coord = $this->pedir(['q' => $texto]);

                if ($coord !== null && ! self::longeDoCodigoPostal($coord, $cp)) {
                    return [$coord, 'morada'];
                }
            }

            if ($cp !== null && ($coord = $this->pedir(['postalcode' => $cp])) !== null && ! self::longeDoCodigoPostal($coord, $cp)) {
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

    /** @return array<int, string> */
    private static function variantes(string $morada, bool $semCodigoPostal = false): array
    {
        if ($morada === '') {
            return [];
        }

        $comPais = fn (string $texto): string => preg_match('/portugal\s*$/iu', $texto) ? $texto : $texto.', Portugal';
        $variantes = [$comPais($morada)];

        // A partir da primeira parte que parece uma rua (R., Rua, Av., Praceta...).
        if (preg_match('/\b(R\.|Rua|Av\.|Avenida|Al\.|Alameda|Pra[cç]a|Praceta|Largo|Travessa|Tv\.|Estrada|Est\.|Lugar|Parque|Quinta)\s.*$/iu', $morada, $m) && $m[0] !== $morada) {
            $variantes[] = $comPais($m[0]);
        }

        // Sem codigo postal, ao menos a localidade (a ultima parte da morada,
        // ex.: "... Vale do Alecrim - Palmela."): chega para ordenar a volta.
        if ($semCodigoPostal && preg_match('/[,\-–]\s*([^,\-–\d]{3,})[.\s]*$/u', $morada, $m)) {
            $variantes[] = $comPais(rtrim(trim($m[1]), '. '));
        }

        return array_values(array_unique($variantes));
    }

    /**
     * Se o ponto fica a mais de 20 km do centro do codigo postal, a morada
     * encontrada e outra com o mesmo nome (ex.: "Av. do Mediterraneo").
     */
    public static function longeDoCodigoPostal(array $coord, ?string $cp): bool
    {
        // So com codigos que estao na tabela: o "mais proximo" pode ser longe.
        if (! preg_match('/(\d{4})/', (string) $cp, $m) || ($centro = config('codigos_postais.'.(int) $m[1])) === null) {
            return false;
        }

        $dLat = deg2rad($coord[0] - $centro[0]);
        $dLng = deg2rad($coord[1] - $centro[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($centro[0])) * cos(deg2rad($coord[0])) * sin($dLng / 2) ** 2;

        return 2 * 6371 * asin(min(1, sqrt($h))) > (float) config('entregas.geocoder_distancia_maxima_km', 20);
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
