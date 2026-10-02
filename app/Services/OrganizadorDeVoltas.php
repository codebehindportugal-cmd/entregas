<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Poe as paragens de uma volta por ordem: primeiro o que tem hora limite
 * cedo (ex.: ate as 7h / 8h), depois o resto pela proximidade, sem chegar
 * antes de abrirem (9h por defeito) nem depois de fecharem (18h por defeito,
 * ou a hora do horario da empresa, ex.: "ate 17:30").
 *
 * As distancias sao os minutos de carro pela estrada (OSRM) entre as
 * moradas de cada paragem ('lat'/'lng', vindas do Geolocalizador). Paragens
 * sem morada localizada usam o centro do codigo postal
 * (config/codigos_postais.php); se o OSRM nao responder, usa-se a linha reta.
 *
 * Cada paragem e um array com pelo menos 'chave', 'cp' e 'horario', e
 * opcionalmente 'lat' e 'lng'.
 */
class OrganizadorDeVoltas
{
    /** Ate esta hora, uma hora limite quer dizer "entregar cedo, antes de abrir". */
    private const LIMITE_CEDO = 10 * 60 + 30;

    private const PENALIZACAO_ATRASO = 1000;

    /** Fracao da penalizacao para atrasos em paragens que fecham a tarde. */
    private const PESO_ATRASO_TARDE = 0.005;

    /** Mais cedo do que isto nao se sai. */
    private const SAIDA_MAIS_CEDO = 4 * 60;

    /** De onde sai a volta que se esta a organizar (null = o armazem). */
    private ?array $partida = null;

    /** Minutos pela estrada entre os pontos da volta (null = linha reta). */
    private ?array $tempos = null;

    /** @var array<string, int> posicao de cada ponto em $tempos */
    private array $indice = [];

    public function __construct(private ?TemposDeEstrada $estrada = null)
    {
        $this->estrada ??= app(TemposDeEstrada::class);
    }

    /** @return Collection<int, array> as paragens pela ordem da volta, com as horas previstas */
    public function organizar(Collection $paragens, ?string $partidaCp = null): Collection
    {
        $this->partida = self::coordenadas($partidaCp);
        [$comLocal, $semLocal] = $this->preparar($paragens)->partition(fn (array $p): bool => $p['_coord'] !== null);
        $this->carregarTempos($comLocal);
        $saida = $this->horaDeSaida($comLocal);

        // Se com esta hora de saida alguma entrega cedo (ate as 7h, 8h...) nao
        // chega a tempo, sai-se mais cedo e volta-se a organizar.
        for ($tentativa = 0; $tentativa < 8; $tentativa++) {
            $ordem = $this->melhorar($this->gulosa($comLocal->values(), $saida), $saida);
            $atraso = $this->atrasoNasEntregasCedo($ordem, $saida);

            if ($atraso <= 0 || $saida <= self::SAIDA_MAIS_CEDO) {
                break;
            }

            $saida = max(self::SAIDA_MAIS_CEDO, $saida - (int) ceil($atraso / 5) * 5);
        }

        $saida = $this->semEsperarNaPrimeira($ordem, $saida);

        // Sem codigo postal nao se sabe onde ficam: vao para o fim.
        return $this->horas($ordem->concat($semLocal)->values(), $saida);
    }

    /** As horas previstas mantendo a ordem que as paragens ja tem. */
    public function simular(Collection $paragens, ?string $partidaCp = null): Collection
    {
        $this->partida = self::coordenadas($partidaCp);
        $preparadas = $this->preparar($paragens);
        $comLocal = $preparadas->filter(fn (array $p): bool => $p['_coord'] !== null)->values();
        $this->carregarTempos($comLocal);
        $saida = $this->horaDeSaida($comLocal);

        for ($tentativa = 0; $tentativa < 8; $tentativa++) {
            $atraso = $this->atrasoNasEntregasCedo($comLocal, $saida);

            if ($atraso <= 0 || $saida <= self::SAIDA_MAIS_CEDO) {
                break;
            }

            $saida = max(self::SAIDA_MAIS_CEDO, $saida - (int) ceil($atraso / 5) * 5);
        }

        return $this->horas($preparadas, $this->semEsperarNaPrimeira($comLocal, $saida));
    }

    /**
     * A hora de saida calculada antes de ordenar e a pensar na paragem mais
     * longe; com a volta ja feita, sai-se so a tempo de chegar a primeira
     * quando abre (sem ficar a espera a porta), se isso nao atrasar nenhuma.
     */
    private function semEsperarNaPrimeira(Collection $ordem, int $saida): int
    {
        $primeira = $ordem->first(fn (array $p): bool => $p['_coord'] !== null);

        // Se a primeira e uma entrega cedo, a saida ja foi acertada para ela.
        if ($primeira === null || $primeira['_fecha'] <= self::LIMITE_CEDO) {
            return $saida;
        }

        $chegar = intdiv(max($primeira['_abre'], self::abrePadrao()) - $this->viagem($this->origem(), $primeira['_coord']), 5) * 5;

        if ($chegar <= $saida) {
            return $saida;
        }

        return $this->atrasoTotal($ordem, $chegar) <= $this->atrasoTotal($ordem, $saida) ? $chegar : $saida;
    }

    private function atrasoTotal(Collection $ordem, int $saida): int
    {
        $t = $saida;
        $pos = $this->origem();
        $atraso = 0;

        foreach ($ordem as $p) {
            if ($p['_coord'] === null) {
                continue;
            }

            $inicio = max($t + $this->viagem($pos, $p['_coord']), $p['_abre']);
            $atraso += max(0, $inicio - $p['_fecha']);
            $t = $inicio + $this->servico();
            $pos = $p['_coord'];
        }

        return $atraso;
    }

    /** O maior atraso (minutos) nas entregas que tem de ser feitas cedo. */
    private function atrasoNasEntregasCedo(Collection $ordem, int $saida): int
    {
        $t = $saida;
        $pos = $this->origem();
        $maior = 0;

        foreach ($ordem as $p) {
            if ($p['_coord'] === null) {
                continue;
            }

            $inicio = max($t + $this->viagem($pos, $p['_coord']), $p['_abre']);

            if ($p['_fecha'] <= self::LIMITE_CEDO) {
                $maior = max($maior, $inicio - $p['_fecha']);
            }

            $t = $inicio + $this->servico();
            $pos = $p['_coord'];
        }

        return $maior;
    }

    /**
     * Janela de entrega [abre, fecha] em minutos desde a meia-noite, a partir
     * do texto do horario ("ate as 8h", "9 e 11h", "depois das 9:30", "ind").
     *
     * @return array{0: int, 1: int}
     */
    public static function janela(?string $horario): array
    {
        [$abre, $fecha] = array_map(
            fn (string $hora): int => self::minutos($hora),
            config('entregas.janela_padrao', ['09:00', '18:00'])
        );
        $texto = mb_strtolower(trim((string) $horario));

        if ($texto === '') {
            return [$abre, $fecha];
        }

        // Indiferente: entrega-se a qualquer hora (ex.: logo a seguir a uma
        // entrega cedo no mesmo sitio), so nao depois de fechar.
        if (in_array($texto, ['ind', 'ind.', 'indiferente', '-', 'qualquer', 'qualquer hora'], true)) {
            return [0, $fecha];
        }

        preg_match_all('/(\d{1,2})(?:\s*[:h.]\s*(\d{2}))?/u', $texto, $m, PREG_SET_ORDER);
        $horas = collect($m)
            ->filter(fn (array $x): bool => (int) $x[1] <= 23)
            ->map(fn (array $x): int => (int) $x[1] * 60 + (int) ($x[2] ?? 0))
            ->values();

        if ($horas->isEmpty()) {
            return [$abre, $fecha];
        }

        if (str_contains($texto, 'depois') || str_contains($texto, 'partir')) {
            return [$horas[0], max($fecha, $horas[0] + 60)];
        }

        if ($horas->count() >= 2) {
            return [min($horas[0], $horas[1]), max($horas[0], $horas[1])];
        }

        // Uma hora so e a hora limite. Cedo (ate as 10h30) entrega-se antes
        // de abrirem; mais tarde (ex.: "ate 17:30") e a hora de fecho.
        return $horas[0] <= self::LIMITE_CEDO ? [0, $horas[0]] : [$abre, $horas[0]];
    }

    /** @return array{0: float, 1: float}|null */
    public static function coordenadas(?string $codigoPostal): ?array
    {
        if (! preg_match('/(\d{4})/', (string) $codigoPostal, $m) || (int) $m[1] < 1000) {
            return null;
        }

        $cp = (int) $m[1];
        $tabela = config('codigos_postais', []);

        if (isset($tabela[$cp])) {
            return $tabela[$cp];
        }

        // O mais proximo abaixo (os codigos crescem por zona), senao o mais proximo.
        $abaixo = collect(array_keys($tabela))->filter(fn (int $c): bool => $c <= $cp && $cp - $c < 50)->max();
        $chave = $abaixo ?? collect(array_keys($tabela))->sortBy(fn (int $c): int => abs($c - $cp))->first();

        return $chave !== null ? $tabela[$chave] : null;
    }

    public static function hora(int $minutos): string
    {
        $minutos = max(0, $minutos);

        return sprintf('%02d:%02d', intdiv($minutos, 60) % 24, $minutos % 60);
    }

    private static function abrePadrao(): int
    {
        return self::minutos((string) (config('entregas.janela_padrao', ['09:00'])[0] ?? '09:00'));
    }

    private static function minutos(string $hora): int
    {
        [$h, $m] = array_pad(explode(':', $hora), 2, 0);

        return (int) $h * 60 + (int) $m;
    }

    private function preparar(Collection $paragens): Collection
    {
        return $paragens->values()->map(function (array $p): array {
            [$abre, $fecha] = self::janela($p['horario'] ?? null);

            $coord = isset($p['lat'], $p['lng']) && $p['lat'] !== null && $p['lng'] !== null
                ? [(float) $p['lat'], (float) $p['lng']]
                : self::coordenadas($p['cp'] ?? null);

            return $p + ['_coord' => $coord, '_abre' => $abre, '_fecha' => $fecha];
        });
    }

    /** @return array{0: float, 1: float} */
    private function origem(): array
    {
        return $this->partida ?? config('entregas.origem_coordenadas', [39.4036, -9.1361]);
    }

    private function servico(): int
    {
        return (int) config('entregas.minutos_por_paragem', 7);
    }

    /**
     * Quando sair do armazem: a tempo da primeira hora limite cedo, ou para
     * chegar a primeira paragem quando abrem.
     */
    private function horaDeSaida(Collection $paragens): int
    {
        $origem = $this->origem();
        $cedo = $paragens->filter(fn (array $p): bool => $p['_fecha'] <= self::LIMITE_CEDO);

        $saida = $cedo->isNotEmpty()
            ? $cedo->map(fn (array $p): int => $p['_fecha'] - $this->viagem($origem, $p['_coord']) - 10)->min()
            : ($paragens->isEmpty() ? 8 * 60 : $paragens->map(fn (array $p): int => max($p['_abre'], self::abrePadrao()) - $this->viagem($origem, $p['_coord']))->min());

        return max(self::SAIDA_MAIS_CEDO, intdiv((int) $saida, 5) * 5);
    }

    /** Vizinho mais proximo que respeita os horarios. */
    private function gulosa(Collection $paragens, int $saida): Collection
    {
        $resto = $paragens->all();
        $ordem = [];
        $t = $saida;
        $pos = $this->origem();

        while ($resto !== []) {
            $escolha = null;
            $melhor = INF;

            foreach ($resto as $i => $p) {
                $viagem = $this->viagem($pos, $p['_coord']);
                $inicio = max($t + $viagem, $p['_abre']);

                if ($inicio > $p['_fecha']) {
                    continue;
                }

                // Indo agora a esta, as outras ainda se conseguem fazer a tempo?
                $fim = $inicio + $this->servico();
                $estraga = false;

                foreach ($resto as $j => $outra) {
                    if ($j === $i || max($t + $this->viagem($pos, $outra['_coord']), $outra['_abre']) > $outra['_fecha']) {
                        continue;
                    }

                    if (max($fim + $this->viagem($p['_coord'], $outra['_coord']), $outra['_abre']) > $outra['_fecha']) {
                        $estraga = true;
                        break;
                    }
                }

                // Se se chega antes de abrirem a espera iguala as distancias: a
                // viagem desempata (vai-se a mais perto).
                $custo = ($inicio - $t) + ($estraga ? 100000 : 0) + $viagem / 100 + $p['_fecha'] / 10000;

                if ($custo < $melhor) {
                    $melhor = $custo;
                    $escolha = $i;
                }
            }

            // Ja nenhuma chega a tempo: a que fecha mais cedo primeiro.
            $escolha ??= collect($resto)->sortBy('_fecha')->keys()->first();

            $p = $resto[$escolha];
            $t = max($t + $this->viagem($pos, $p['_coord']), $p['_abre']) + $this->servico();
            $pos = $p['_coord'];
            $ordem[] = $p;
            unset($resto[$escolha]);
        }

        return collect($ordem);
    }

    /** Melhora a ordem trocando troços (2-opt) e mudando paragens de sitio. */
    private function melhorar(Collection $ordem, int $saida): Collection
    {
        $atual = $ordem->values()->all();
        $n = count($atual);

        if ($n < 2) {
            return collect($atual);
        }

        $custoAtual = $this->custo($atual, $saida);

        for ($volta = 0; $volta < 50; $volta++) {
            $melhorou = false;

            for ($i = 0; $i < $n - 1; $i++) {
                for ($k = $i + 1; $k < $n; $k++) {
                    $tentativa = array_merge(
                        array_slice($atual, 0, $i),
                        array_reverse(array_slice($atual, $i, $k - $i + 1)),
                        array_slice($atual, $k + 1)
                    );
                    $custo = $this->custo($tentativa, $saida);

                    if ($custo + 0.01 < $custoAtual) {
                        [$atual, $custoAtual, $melhorou] = [$tentativa, $custo, true];
                    }
                }
            }

            // Mudar uma paragem, ou um grupo de 2 a 4 seguidas, para outro sitio.
            for ($tamanho = 1; $tamanho <= min(4, $n - 1); $tamanho++) {
                for ($i = 0; $i + $tamanho <= $n; $i++) {
                    for ($j = 0; $j <= $n - $tamanho; $j++) {
                        if ($i === $j) {
                            continue;
                        }

                        $tentativa = $atual;
                        $movidas = array_splice($tentativa, $i, $tamanho);
                        array_splice($tentativa, $j, 0, $movidas);
                        $custo = $this->custo($tentativa, $saida);

                        if ($custo + 0.01 < $custoAtual) {
                            [$atual, $custoAtual, $melhorou] = [$tentativa, $custo, true];
                        }
                    }
                }
            }

            if (! $melhorou) {
                break;
            }
        }

        return collect($atual);
    }

    /** Hora a que acaba a volta mais os minutos a conduzir, com muito peso nos minutos de atraso. */
    private function custo(array $ordem, int $saida): float
    {
        $t = $saida;
        $pos = $this->origem();
        $atraso = 0;
        $somaHoras = 0;
        $conducao = 0;

        foreach ($ordem as $p) {
            $viagem = $this->viagem($pos, $p['_coord']);
            $conducao += $viagem;
            $inicio = max($t + $viagem, $p['_abre']);
            // Chegar tarde a uma entrega cedo (ate as 8h...) e muito grave; a
            // uma que fecha a tarde pesa menos, para que uma paragem que nunca
            // da para chegar a horas (longe, fim do dia) nao baralhe a volta
            // toda so para ganhar uns minutos nessa.
            $atraso += max(0, $inicio - $p['_fecha']) * ($p['_fecha'] <= self::LIMITE_CEDO ? 1 : self::PESO_ATRASO_TARDE);
            $somaHoras += $inicio;
            $t = $inicio + $this->servico();
            $pos = $p['_coord'];
        }

        // A volta acaba de volta a partida (o armazem): conta o caminho de
        // regresso. Sem isto a volta comecava pela paragem mais perto da
        // partida e acabava na mais longe, as vezes ao contrario do caminho
        // natural (ex.: Lisboa a comecar em Moscavide e a acabar no centro).
        if ($ordem !== [] && config('entregas.volta_regressa_a_partida', true)) {
            $regresso = $this->viagem($pos, $this->origem());
            $t += $regresso;
            $conducao += $regresso;
        }

        // Um pouco de peso em entregar cedo: entre duas voltas parecidas, faz
        // primeiro os sitios com muitas entregas juntas e fica com folga no fim.
        // Os minutos ao volante contam a parte: com a saida fixa, uma volta que
        // anda mais e depois espera a porta acabava a mesma hora que a curta.
        return $t + $conducao + $somaHoras * 0.02 + $atraso * self::PENALIZACAO_ATRASO;
    }

    private function horas(Collection $ordem, int $saida): Collection
    {
        $t = $saida;
        $pos = $this->origem();

        return $ordem->map(function (array $p) use (&$t, &$pos, $saida): array {
            $chegada = $t + $this->viagem($pos, $p['_coord']);
            $inicio = max($chegada, $p['_abre']);
            $t = $inicio + $this->servico();
            $pos = $p['_coord'] ?? $pos;

            return collect($p)->except(['_coord', '_abre', '_fecha'])->all() + [
                'saida' => self::hora($saida),
                'hora_prevista' => $p['_coord'] === null ? null : self::hora($inicio),
                'espera' => max(0, $p['_abre'] - $chegada),
                'abre' => self::hora($p['_abre']),
                'fecha' => self::hora($p['_fecha']),
                'atrasada' => $p['_coord'] !== null && $inicio > $p['_fecha'],
            ];
        })->values();
    }

    /** Pede ao OSRM os minutos entre a partida e todas as paragens da volta. */
    private function carregarTempos(Collection $paragens): void
    {
        $this->tempos = null;
        $this->indice = [];
        $pontos = [];

        foreach ($paragens->pluck('_coord')->prepend($this->origem()) as $coord) {
            $chave = self::chaveDoPonto($coord);

            if (! isset($this->indice[$chave])) {
                $this->indice[$chave] = count($pontos);
                $pontos[] = $coord;
            }
        }

        $this->tempos = count($pontos) > 1 ? $this->estrada?->matriz($pontos) : null;
    }

    private static function chaveDoPonto(array $coord): string
    {
        return sprintf('%.5f,%.5f', $coord[0], $coord[1]);
    }

    /** Minutos de carro entre dois pontos (pela estrada, ou estimativa). */
    private function viagem(?array $a, ?array $b): int
    {
        if ($a === null || $b === null) {
            return 15;
        }

        if ($this->tempos !== null) {
            $i = $this->indice[self::chaveDoPonto($a)] ?? null;
            $j = $this->indice[self::chaveDoPonto($b)] ?? null;

            if ($i !== null && $j !== null && isset($this->tempos[$i][$j])) {
                return $this->tempos[$i][$j];
            }
        }

        $raio = 6371;
        $dLat = deg2rad($b[0] - $a[0]);
        $dLng = deg2rad($b[1] - $a[1]);
        $h = sin($dLat / 2) ** 2 + cos(deg2rad($a[0])) * cos(deg2rad($b[0])) * sin($dLng / 2) ** 2;
        $km = 2 * $raio * asin(min(1, sqrt($h))) * 1.25;

        if ($km < 0.3) {
            return 2;
        }

        // Perto anda-se devagar (cidade), longe vai-se por autoestrada.
        $velocidade = 30 + min($km, 80) * 0.8;

        return (int) round($km / $velocidade * 60) + 4;
    }
}
