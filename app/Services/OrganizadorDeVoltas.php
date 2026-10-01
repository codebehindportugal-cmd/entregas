<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Poe as paragens de uma volta por ordem: primeiro o que tem hora limite
 * cedo (ex.: ate as 7h / 8h), depois o resto pela proximidade, sem chegar
 * antes de abrirem (9h por defeito) nem depois de fecharem (18h por defeito,
 * ou a hora do horario da empresa, ex.: "ate 17:30").
 *
 * As distancias sao em linha reta entre o centro dos codigos postais
 * (config/codigos_postais.php), por isso as horas sao estimativas.
 *
 * Cada paragem e um array com pelo menos 'chave', 'cp' e 'horario'.
 */
class OrganizadorDeVoltas
{
    /** Ate esta hora, uma hora limite quer dizer "entregar cedo, antes de abrir". */
    private const LIMITE_CEDO = 10 * 60 + 30;

    private const PENALIZACAO_ATRASO = 1000;

    /** De onde sai a volta que se esta a organizar (null = o armazem). */
    private ?array $partida = null;

    /** @return Collection<int, array> as paragens pela ordem da volta, com as horas previstas */
    public function organizar(Collection $paragens, ?string $partidaCp = null): Collection
    {
        $this->partida = self::coordenadas($partidaCp);
        [$comLocal, $semLocal] = $this->preparar($paragens)->partition(fn (array $p): bool => $p['_coord'] !== null);
        $saida = $this->horaDeSaida($comLocal);

        $ordem = $this->melhorar($this->gulosa($comLocal->values(), $saida), $saida);

        // Sem codigo postal nao se sabe onde ficam: vao para o fim.
        return $this->horas($ordem->concat($semLocal)->values(), $saida);
    }

    /** As horas previstas mantendo a ordem que as paragens ja tem. */
    public function simular(Collection $paragens, ?string $partidaCp = null): Collection
    {
        $this->partida = self::coordenadas($partidaCp);
        $preparadas = $this->preparar($paragens);

        return $this->horas($preparadas, $this->horaDeSaida($preparadas->filter(fn (array $p): bool => $p['_coord'] !== null)));
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

        if ($texto === '' || in_array($texto, ['ind', 'indiferente', '-', 'qualquer'], true)) {
            return [$abre, $fecha];
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

    private static function minutos(string $hora): int
    {
        [$h, $m] = array_pad(explode(':', $hora), 2, 0);

        return (int) $h * 60 + (int) $m;
    }

    private function preparar(Collection $paragens): Collection
    {
        return $paragens->values()->map(function (array $p): array {
            [$abre, $fecha] = self::janela($p['horario'] ?? null);

            return $p + ['_coord' => self::coordenadas($p['cp'] ?? null), '_abre' => $abre, '_fecha' => $fecha];
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
            : ($paragens->isEmpty() ? 8 * 60 : $paragens->map(fn (array $p): int => $p['_abre'] - $this->viagem($origem, $p['_coord']))->min());

        return max(4 * 60, intdiv((int) $saida, 5) * 5);
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
                $inicio = max($t + $this->viagem($pos, $p['_coord']), $p['_abre']);

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

                $custo = ($inicio - $t) + ($estraga ? 100000 : 0) + $p['_fecha'] / 10000;

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

        if ($n < 3) {
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

    /** Hora a que acaba a volta, com muito peso nos minutos de atraso. */
    private function custo(array $ordem, int $saida): float
    {
        $t = $saida;
        $pos = $this->origem();
        $atraso = 0;
        $somaHoras = 0;

        foreach ($ordem as $p) {
            $inicio = max($t + $this->viagem($pos, $p['_coord']), $p['_abre']);
            $atraso += max(0, $inicio - $p['_fecha']);
            $somaHoras += $inicio;
            $t = $inicio + $this->servico();
            $pos = $p['_coord'];
        }

        // Um pouco de peso em entregar cedo: entre duas voltas parecidas, faz
        // primeiro os sitios com muitas entregas juntas e fica com folga no fim.
        return $t + $somaHoras * 0.1 + $atraso * self::PENALIZACAO_ATRASO;
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

    /** Minutos de carro entre dois pontos (estimativa). */
    private function viagem(?array $a, ?array $b): int
    {
        if ($a === null || $b === null) {
            return 15;
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
