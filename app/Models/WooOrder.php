<?php

namespace App\Models;

use Database\Factories\WooOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

class WooOrder extends Model
{
    /** @use HasFactory<WooOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'woo_id',
        'source_type',
        'ordered_at',
        'status',
        'total',
        'billing_name',
        'billing_phone',
        'billing_email',
        'customer_language',
        'line_items',
        'postponed_until',
        'postponement_history',
        'next_payment_at',
        'first_delivery_at',
        'delivery_dates',
        'cancelled_delivery_dates',
        'subscription_ends_at',
        'excluded_products',
        'preferences_text',
        'profile_preferences',
        'customer_notes',
        'dia_entrega',
        'cabaz_tipo',
        'ciclo_entrega',
        'scheduled_delivery_at',
        'raw_payload',
        'synced_at',
        'fatura_document_id',
        'fatura_tipo',
        'fatura_emitida_em',
        'cabaz_itens_faturados',
        'fatura_produtos',
    ];

    protected function casts(): array
    {
        return [
            'line_items' => 'array',
            'excluded_products' => 'array',
            'raw_payload' => 'array',
            'postponed_until' => 'date',
            'postponement_history' => 'array',
            'next_payment_at' => 'date',
            'first_delivery_at' => 'date',
            'delivery_dates' => 'array',
            'cancelled_delivery_dates' => 'array',
            'subscription_ends_at' => 'date',
            'ordered_at' => 'datetime',
            'scheduled_delivery_at' => 'date',
            'synced_at' => 'datetime',
            'total' => 'decimal:2',
            'fatura_emitida_em' => 'datetime',
            'cabaz_itens_faturados' => 'array',
            'fatura_produtos' => 'array',
        ];
    }

    public function preparacaoItems(): HasMany
    {
        return $this->hasMany(PreparacaoItem::class);
    }

    public function registoEntregas(): HasMany
    {
        return $this->hasMany(RegistoEntrega::class);
    }

    public function podeConcluirNoWordPress(): bool
    {
        if (in_array($this->status, ['completed', 'wc-completed'], true)) {
            return false;
        }

        if ($this->isSubscricao()) {
            $entregas = $this->entregasSubscricao();
            $total = (int) ($entregas['total'] ?? 0);

            return $total > 0
                && (int) ($entregas['feitas'] ?? 0) >= $total
                && (int) ($entregas['por_realizar'] ?? 0) === 0;
        }

        $registos = $this->registosEntregaParaConclusao();

        if ($registos->isEmpty()) {
            return false;
        }

        return $registos->contains(fn (RegistoEntrega $registo): bool => $registo->status === 'entregue');
    }

    public function isSubscricao(): bool
    {
        return $this->source_type === 'subscription'
            || in_array($this->status, ['subscricao', 'wc-subscricao', 'active'], true);
    }

    public function temEntregaB2cNaData(string|Carbon $data): bool
    {
        if (in_array($this->status, ['completed', 'wc-completed'], true)) {
            return false;
        }

        $dataEntrega = Carbon::parse($data)->toDateString();

        if ($this->postponed_until !== null) {
            return $this->postponed_until->toDateString() === $dataEntrega;
        }

        if ($this->isSubscricao()) {
            return $this->datasSubscricao()->contains($dataEntrega);
        }

        if ($this->scheduled_delivery_at !== null) {
            return $this->scheduled_delivery_at->toDateString() === $dataEntrega;
        }

        return $this->diaEntregaCoincideComData($dataEntrega);
    }

    private function registosEntregaParaConclusao(): Collection
    {
        if ($this->relationLoaded('registoEntregas')) {
            return $this->registoEntregas;
        }

        if (! $this->exists) {
            return collect();
        }

        return $this->registoEntregas()->get();
    }

    public static function detectarCabazTipo(array $lineItems): ?string
    {
        $nomeLower = mb_strtolower(collect($lineItems)->pluck('name')->implode(' '));

        if (str_contains($nomeLower, 'solo') || str_contains($nomeLower, 'mini')) {
            return 'mini';
        }

        if (str_contains($nomeLower, 'grande')) {
            return 'grande';
        }

        if (str_contains($nomeLower, 'medio') || str_contains($nomeLower, 'médio')) {
            return 'medio';
        }

        if (str_contains($nomeLower, 'pequeno')) {
            return 'pequeno';
        }

        // O mini tambem aparece no site como "solo".
        if (str_contains($nomeLower, 'mini') || str_contains($nomeLower, 'solo')) {
            return 'mini';
        }

        return null;
    }

    public function entregasSubscricao(): array
    {
        if (
            $this->first_delivery_at !== null
            && $this->subscription_ends_at !== null
            && $this->first_delivery_at->greaterThan($this->subscription_ends_at)
        ) {
            return [
                'total' => 0,
                'feitas' => 0,
                'por_realizar' => 0,
                'proxima' => null,
            ];
        }

        $datas = $this->datasSubscricao();
        $canceladas = collect($this->cancelled_delivery_dates ?? [])
            ->filter()
            ->map(fn (string $data) => Carbon::parse($data)->toDateString())
            ->all();

        $concluidas = $this->datasConcluidasSubscricao();

        $hoje = now()->toDateString();
        $postponedUntil = $this->postponed_until?->toDateString();
        $adiamentoJaAplicadoNoCalendario = $postponedUntil !== null && $datas->contains($postponedUntil);
        $dataAdiada = $postponedUntil === null || $adiamentoJaAplicadoNoCalendario
            ? null
            : $datas
                ->reject(fn (string $data) => in_array($data, $concluidas, true))
                ->reject(fn (string $data) => in_array($data, $canceladas, true))
                ->filter(fn (string $data) => $data < $postponedUntil)
                ->last();

        $feitas = $datas->filter(fn (string $data): bool => $this->entregaContaComoFeita(
            $data,
            $dataAdiada,
            $concluidas,
            $canceladas,
        ));
        $porRealizar = $datas->reject(fn (string $data): bool => in_array($data, $canceladas, true) || $this->entregaContaComoFeita(
            $data,
            $dataAdiada,
            $concluidas,
            $canceladas,
        ));
        $referenciaProxima = $postponedUntil !== null && $postponedUntil > $hoje ? $postponedUntil : $hoje;
        $proxima = $dataAdiada !== null
            ? $postponedUntil
            : ($porRealizar->first(fn (string $data) => $data >= $referenciaProxima)
                ?? $porRealizar->first());

        return [
            'total' => $datas->count(),
            'feitas' => $feitas->count(),
            'por_realizar' => $porRealizar->count(),
            'proxima' => $proxima ?? ($adiamentoJaAplicadoNoCalendario ? null : $postponedUntil),
        ];
    }

    public function calendarioSubscricao(): Collection
    {
        $datas = $this->datasSubscricao();
        $canceladas = collect($this->cancelled_delivery_dates ?? [])
            ->filter()
            ->map(fn (string $data) => Carbon::parse($data)->toDateString())
            ->all();
        $concluidas = $this->datasConcluidasSubscricao();
        $postponedUntil = $this->postponed_until?->toDateString();

        if ($postponedUntil !== null && ! $datas->contains($postponedUntil)) {
            $datas = $datas->push($postponedUntil)->sort()->values();
        }

        return $datas
            ->map(function (string $data) use ($canceladas, $concluidas, $postponedUntil): array {
                $date = Carbon::parse($data);
                $status = match (true) {
                    in_array($data, $canceladas, true) => 'cancelada',
                    in_array($data, $concluidas, true) => 'entregue',
                    $postponedUntil === $data => 'adiada',
                    // Enquanto o módulo de rotas/colaboradores não está operacional,
                    // tratamos entregas passadas sem status definido como "entregue".
                    $date->isPast() && ! $date->isToday() => 'entregue',
                    default => 'por_realizar',
                };

                return [
                    'data' => $date,
                    'data_key' => $data,
                    'status' => $status,
                    'label' => match ($status) {
                        'cancelada' => 'Cancelada',
                        'entregue' => 'Entregue',
                        'adiada' => 'Adiada',
                        'em_atraso' => 'Em atraso',
                        default => 'Por realizar',
                    },
                ];
            })
            ->values();
    }

    public function calendarioEntregas(): Collection
    {
        if ($this->isSubscricao()) {
            return $this->calendarioSubscricao();
        }

        $registos = $this->registosEntregaParaConclusao()
            ->mapWithKeys(fn (RegistoEntrega $registo): array => [
                Carbon::parse($registo->data_entrega)->toDateString() => $registo->status,
            ]);
        $datas = collect([
            $this->scheduled_delivery_at?->toDateString(),
            $this->postponed_until?->toDateString(),
        ])
            ->merge($registos->keys())
            ->filter()
            ->unique()
            ->sort()
            ->values();

        if ($datas->isEmpty() && $this->dia_entrega !== null) {
            $data = now()->startOfDay();

            for ($i = 0; $i < 21; $i++) {
                if ($this->diaEntregaCoincideComData($data->toDateString())) {
                    $datas->push($data->toDateString());
                    break;
                }

                $data->addDay();
            }
        }

        $postponedUntil = $this->postponed_until?->toDateString();

        return $datas
            ->map(function (string $data) use ($registos, $postponedUntil): array {
                $date = Carbon::parse($data);
                $registoStatus = $registos->get($data);
                $status = match (true) {
                    $registoStatus === 'entregue' => 'entregue',
                    $registoStatus === 'falhou' => 'em_atraso',
                    $postponedUntil === $data => 'adiada',
                    $date->isPast() && ! $date->isToday() => 'em_atraso',
                    default => 'por_realizar',
                };

                return [
                    'data' => $date,
                    'data_key' => $data,
                    'status' => $status,
                    'label' => match ($status) {
                        'entregue' => 'Entregue',
                        'adiada' => 'Adiada',
                        'em_atraso' => 'Em atraso',
                        default => 'Por realizar',
                    },
                ];
            })
            ->values();
    }

    private function entregaContaComoFeita(
        string $data,
        ?string $dataAdiada,
        array $concluidas,
        array $canceladas,
    ): bool {
        if (in_array($data, $canceladas, true) || $data === $dataAdiada) {
            return false;
        }

        if (in_array($data, $concluidas, true)) {
            return true;
        }

        // Consistente com calendarioSubscricao(): enquanto o modulo de registos de
        // entrega nao esta operacional, as datas passadas (excepto hoje) contam como
        // entregues. Sem isto, a contagem de "Feitas" ficava sistematicamente errada
        // (mais baixa) do que as datas marcadas como entregues no calendario.
        return $data < now()->toDateString();
    }

    public function fimCicloSubscricao(): ?Carbon
    {
        $ultimaEntrega = $this->datasSubscricao()->last();

        if ($ultimaEntrega !== null) {
            return Carbon::parse($ultimaEntrega);
        }

        return $this->subscription_ends_at;
    }

    public function proximaEncomendaSubscricao(): ?Carbon
    {
        if (($this->entregasSubscricao()['por_realizar'] ?? 0) === 0) {
            return null;
        }

        if ($this->next_payment_at !== null) {
            return $this->next_payment_at;
        }

        $fim = $this->fimCicloSubscricao();

        if ($fim === null) {
            return null;
        }

        return $fim->copy()->addWeeks($this->semanasPorCiclo());
    }

    /**
     * Numero de semanas que um adiamento salta por defeito: 1 nas subscricoes
     * semanais, 2 nas de 15 em 15 dias. E o ciclo do cliente que manda.
     */
    public function semanasDeAdiamentoSugeridas(): int
    {
        return $this->semanasPorCiclo();
    }

    /**
     * Primeira entrega ainda por realizar (hoje ou depois). Usada como base
     * quando se adia sem indicar qual a entrega.
     */
    public function proximaEntregaPorRealizar(): ?string
    {
        $hoje = now()->toDateString();

        $canceladas = collect($this->cancelled_delivery_dates ?? [])
            ->filter()
            ->map(fn (string $data): string => Carbon::parse($data)->toDateString())
            ->all();

        $preparadas = $this->preparacaoItemsParaAdiamento()
            ->where('feito', true)
            ->map(fn (PreparacaoItem $item): string => Carbon::parse($item->data_preparacao)->toDateString())
            ->all();

        return $this->datasSubscricao()
            ->reject(fn (string $data): bool => in_array($data, $canceladas, true))
            ->reject(fn (string $data): bool => in_array($data, $preparadas, true))
            ->first(fn (string $data): bool => $data >= $hoje);
    }

    /**
     * Data resultante de adiar uma entrega N semanas, caindo sempre no dia de
     * entrega do cliente (segunda / quarta / sabado).
     */
    public function dataAdiadaEmSemanas(?string $dataOriginal, int $semanas): ?string
    {
        $semanas = max(1, $semanas);

        $base = $dataOriginal
            ?: $this->proximaEntregaPorRealizar()
            ?: $this->postponed_until?->toDateString()
            ?: $this->first_delivery_at?->toDateString();

        if (blank($base)) {
            return null;
        }

        $data = Carbon::parse($base)->startOfDay()->addWeeks($semanas);
        $diaSemana = $this->diaSemanaSubscricao($data);

        while ($data->dayOfWeek !== $diaSemana) {
            $data->addDay();
        }

        return $data->toDateString();
    }

    public function adiarProximaEntregaPara(string|Carbon $data): void
    {
        $novaData = Carbon::parse($data)->toDateString();
        $datas = $this->datasSubscricao();
        $datas = $this->datasBaseParaAdiamento($datas, $novaData);

        if ($datas->isEmpty()) {
            $this->guardarAdiamento(['postponed_until' => $novaData]);

            return;
        }

        $canceladas = collect($this->cancelled_delivery_dates ?? [])
            ->filter()
            ->map(fn (string $data) => Carbon::parse($data)->toDateString())
            ->all();

        $preparadas = $this->preparacaoItemsParaAdiamento()
            ->where('feito', true)
            ->map(fn (PreparacaoItem $item) => Carbon::parse($item->data_preparacao)->toDateString())
            ->all();

        $hoje = now()->toDateString();
        $datasPorAdiar = $datas
            ->reject(fn (string $data) => in_array($data, $preparadas, true))
            ->reject(fn (string $data) => in_array($data, $canceladas, true))
            ->values();
        $dataOriginal = $novaData < $hoje
            ? $datasPorAdiar->last(fn (string $data) => $data < $novaData)
            : $datasPorAdiar->first(fn (string $data) => $data >= $hoje);
        $dataSemanasAntes = Carbon::parse($novaData)->subWeeks($this->semanasPorCiclo())->toDateString();
        $recalcularPosteriores = $datas->contains($novaData) && ! $datas->contains($dataSemanasAntes);

        $dataOriginal ??= $datasPorAdiar->first();
        $dataOriginal = $recalcularPosteriores ? $novaData : $dataOriginal;

        if ($dataOriginal === null) {
            $this->guardarAdiamento(['postponed_until' => $novaData]);

            return;
        }

        $novasDatas = $this->substituirDataDaSubscricao($datas, $dataOriginal, $novaData, $recalcularPosteriores);
        $dataFim = $this->fimDepoisDoAdiamento($novasDatas);

        $this->guardarAdiamento([
            'delivery_dates' => $novasDatas,
            'next_payment_at' => $this->next_payment_at?->toDateString(),
            'subscription_ends_at' => $dataFim,
            'postponed_until' => $novaData,
            'postponement_history' => $this->historicoComAdiamento($dataOriginal, $novaData),
        ]);
    }

    public function adiarEntregaDaSubscricaoPara(string|Carbon $dataOriginal, string|Carbon $dataNova): void
    {
        $dataOriginal = Carbon::parse($dataOriginal)->toDateString();
        $novaData = Carbon::parse($dataNova)->toDateString();
        $datas = $this->datasSubscricao();

        if (! $datas->contains($dataOriginal)) {
            $this->adiarProximaEntregaPara($novaData);

            return;
        }

        $novasDatas = $this->substituirDataDaSubscricao($datas, $dataOriginal, $novaData);
        $dataFim = $this->fimDepoisDoAdiamento($novasDatas);

        $this->guardarAdiamento([
            'delivery_dates' => $novasDatas,
            'next_payment_at' => $this->next_payment_at?->toDateString(),
            'subscription_ends_at' => $dataFim,
            'postponed_until' => $novaData,
            'postponement_history' => $this->historicoComAdiamento($dataOriginal, $novaData),
        ]);
    }

    public function adiarEncomendaNormalPara(string|Carbon $data): void
    {
        $novaData = Carbon::parse($data)->toDateString();
        $dataAtual = $this->postponed_until?->toDateString()
            ?? $this->scheduled_delivery_at?->toDateString()
            ?? $this->ordered_at?->toDateString();

        $historico = $dataAtual !== null && $dataAtual !== $novaData
            ? $this->historicoComAdiamento($dataAtual, $novaData)
            : collect($this->postponement_history ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->values()
                ->all();

        $this->forceFill([
            'postponed_until' => $novaData,
            'scheduled_delivery_at' => $novaData,
            'postponement_history' => $historico,
        ])->save();
    }

    public function removerAdiamentoEncomendaNormal(): void
    {
        $historico = collect($this->postponement_history ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values();

        $primeiraDataOriginal = $historico
            ->pluck('from')
            ->filter()
            ->first();

        $this->forceFill([
            'postponed_until' => null,
            'scheduled_delivery_at' => $primeiraDataOriginal ?: $this->scheduled_delivery_at?->toDateString(),
            'postponement_history' => [],
        ])->save();
    }

    /**
     * Fim da subscricao depois de um adiamento.
     *
     * Uma subscricao SEM data de fim continua sem data de fim — antes o
     * adiamento gravava como fim a ultima data gerada, o que transformava uma
     * subscricao aberta numa que acabava (e deixava de aparecer na preparacao).
     * Tendo fim, so se estica se as novas datas passarem para la dele.
     *
     * @param  array<int,string>  $novasDatas
     */
    private function fimDepoisDoAdiamento(array $novasDatas): ?string
    {
        $fimAtual = $this->subscription_ends_at?->toDateString();

        if ($fimAtual === null) {
            return null;
        }

        $ultima = collect($novasDatas)->filter()->last();

        return $ultima !== null && $ultima > $fimAtual ? $ultima : $fimAtual;
    }

    private function substituirDataDaSubscricao(Collection $datas, string $dataOriginal, string $novaData, bool $recalcularPosteriores = false): array
    {
        $anteriores = $datas
            ->takeUntil(fn (string $data): bool => $data === $dataOriginal)
            ->values();
        $posteriores = $datas
            ->slice($anteriores->count() + 1)
            ->values();
        $novasDatas = $anteriores->push($novaData);
        $ultimaData = Carbon::parse($novaData);
        $diaSemana = $this->diaSemanaSubscricao($ultimaData);

        foreach ($posteriores as $dataOriginalPosterior) {
            $data = Carbon::parse($dataOriginalPosterior);

            if ($recalcularPosteriores || $data->lessThanOrEqualTo($ultimaData) || $ultimaData->diffInDays($data) < $this->diasMinimosEntreEntregas()) {
                $data = $ultimaData->copy()->addWeeks($this->semanasPorCiclo());

                while ($data->dayOfWeek !== $diaSemana) {
                    $data->addDay();
                }
            }

            $novasDatas->push($data->toDateString());
            $ultimaData = $data;
        }

        return $novasDatas
            ->values()
            ->all();
    }

    private function datasBaseParaAdiamento(Collection $datas, string $novaData): Collection
    {
        if ($datas->isNotEmpty() || $this->first_delivery_at === null || $novaData >= now()->toDateString()) {
            return $datas;
        }

        return $this->gerarDatasDoCiclo(max(1, $datas->count()), false);
    }

    private function gerarDatasDoCiclo(?int $total = null, bool $respeitarFim = true): Collection
    {
        if ($this->first_delivery_at === null) {
            return collect();
        }

        $fim = $respeitarFim ? $this->subscription_ends_at?->copy()->startOfDay() : null;

        if ($fim !== null && $this->first_delivery_at->greaterThan($fim)) {
            return collect();
        }

        // Quando ninguem pede um numero de datas, gera-se ate ao fim da
        // subscricao ou, se nao houver fim, ate um horizonte a frente de HOJE.
        // Antes eram sempre 4 datas: uma subscricao sem fim ficava presa nas
        // primeiras quatro entregas e desaparecia da preparacao a partir dai.
        $limiteDatas = $total ?? 520;
        $horizonte = $total !== null
            ? null
            : ($fim ?? now()->copy()->startOfDay()->addWeeks((int) config('entregas.horizonte_subscricao_semanas', 8)));

        $data = $this->first_delivery_at->copy()->startOfDay();
        $diaSemana = $this->diaSemanaSubscricao($data);

        // Avança até ao primeiro dia de entrega correto (a partir da data de início).
        // Se a data de início já cair nesse dia da semana, mantém-na; caso contrário,
        // avança para o próximo occurrence desse dia da semana.
        while ($data->dayOfWeek !== $diaSemana) {
            $data->addDay();
        }

        $datas = collect([$data->toDateString()]);

        while ($datas->count() < $limiteDatas) {
            $data = $data->copy()->addWeeks($this->semanasPorCiclo());

            while ($data->dayOfWeek !== $diaSemana) {
                $data->addDay();
            }

            if ($fim !== null && $data->greaterThan($fim)) {
                break;
            }

            if ($horizonte !== null && $data->greaterThan($horizonte)) {
                break;
            }

            $datas->push($data->toDateString());
        }

        return $datas;
    }

    private function guardarAdiamento(array $attributes): void
    {
        $this->forceFill($attributes);

        if ($this->exists) {
            $this->save();
        }
    }

    private function historicoComAdiamento(string $dataOriginal, string $novaData): array
    {
        $historico = collect($this->postponement_history ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->values();

        if ($dataOriginal !== $novaData) {
            $historico->push([
                'from' => $dataOriginal,
                'to' => $novaData,
                'changed_at' => now()->toDateTimeString(),
            ]);
        }

        return $historico->values()->all();
    }

    private function preparacaoItemsParaAdiamento(): Collection
    {
        if ($this->relationLoaded('preparacaoItems')) {
            return $this->preparacaoItems;
        }

        if (! $this->exists) {
            return collect();
        }

        return $this->preparacaoItems()->get();
    }

    private function datasConcluidasSubscricao(): array
    {
        $preparadas = $this->preparacaoItemsParaAdiamento()
            ->where('feito', true)
            ->map(fn (PreparacaoItem $item) => Carbon::parse($item->data_preparacao)->toDateString());

        $entregues = $this->registosEntregaParaConclusao()
            ->where('status', 'entregue')
            ->map(fn (RegistoEntrega $registo) => Carbon::parse($registo->data_entrega)->toDateString());

        return $preparadas
            ->merge($entregues)
            ->unique()
            ->values()
            ->all();
    }

    private function datasSubscricao(): Collection
    {
        $datas = collect($this->delivery_dates ?? [])
            ->filter()
            ->map(fn (string $data) => Carbon::parse($data)->toDateString())
            ->sort()
            ->values();

        if ($datas->isNotEmpty() || $this->first_delivery_at === null) {
            return $datas;
        }

        return $this->gerarDatasDoCiclo();
    }

    private function diaSemanaSubscricao(Carbon $fallback): int
    {
        return match ($this->dia_entrega) {
            'segunda' => 1,
            'quarta' => 3,
            'sabado' => 6,
            default => $fallback->dayOfWeek,
        };
    }

    private function diaEntregaCoincideComData(string $data): bool
    {
        if ($this->dia_entrega === null) {
            return false;
        }

        $dayOfWeek = Carbon::parse($data)->dayOfWeek;

        return match ($this->dia_entrega) {
            'segunda' => $dayOfWeek === 1,
            'quarta' => $dayOfWeek === 3,
            'sabado' => $dayOfWeek === 6,
            default => false,
        };
    }

    private function semanasPorCiclo(): int
    {
        return $this->ciclo_entrega === 'quinzenal' ? 2 : 1;
    }

    private function diasMinimosEntreEntregas(): int
    {
        return $this->semanasPorCiclo() === 2 ? 7 : 4;
    }

    public function whatsappRenovacaoUrl(): ?string
    {
        $telefone = preg_replace('/\D+/', '', (string) $this->billing_phone);

        if (blank($telefone)) {
            return null;
        }

        if (str_starts_with($telefone, '9')) {
            $telefone = '351'.$telefone;
        }

        $nome = $this->billing_name ?: ($this->prefersEnglish() ? 'there' : 'cliente');
        $mensagem = $this->prefersEnglish()
            ? "Hi {$nome}! We hope you enjoyed your Horta da Maria subscription. Your subscription is coming to an end and we wanted to confirm whether you would like to renew it to keep receiving your deliveries. If you would like, we can send you the renewal link now. Shall we send it?"
            : "Ola {$nome}! Esperamos que tenha gostado da sua subscricao da Horta da Maria. A sua subscricao esta a terminar e queriamos confirmar se pretende renovar para continuar a receber as suas entregas. Se quiser, podemos enviar-lhe ja o link de renovacao. Deseja que enviemos?";

        return 'https://wa.me/'.$telefone.'?text='.rawurlencode($mensagem);
    }

    public function paymentUrl(): ?string
    {
        $url = $this->raw_payload['payment_url'] ?? null;

        if (filled($url)) {
            return $url;
        }

        $orderKey = $this->raw_payload['order_key'] ?? null;

        if (blank($orderKey) || blank($this->woo_id)) {
            return null;
        }

        return rtrim((string) config('woocommerce.url'), '/')."/checkout/order-pay/{$this->woo_id}/?pay_for_order=true&key={$orderKey}";
    }

    public function moloniDocumentIds(): array
    {
        return collect($this->raw_payload['meta_data'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item) && ($item['key'] ?? null) === '_moloni_sent')
            ->map(fn (array $item): int => (int) ($item['value'] ?? 0))
            ->filter(fn (int $documentId): bool => $documentId > 0)
            ->values()
            ->all();
    }

    public function moloniDocumentId(): ?int
    {
        $documentId = collect($this->moloniDocumentIds())->last();

        return $documentId ?: null;
    }

    public function moloniDocumentUrl(): ?string
    {
        $documentId = $this->moloniDocumentId();

        if ($documentId === null) {
            return null;
        }

        return $this->moloniAdminUrl('getInvoice', $documentId);
    }

    public function moloniDownloadDocumentUrl(): ?string
    {
        $documentId = $this->moloniDocumentId();

        if ($documentId === null) {
            return null;
        }

        return $this->moloniAdminUrl('downloadDocument', $documentId);
    }

    public function moloniGenerateDocumentUrl(): ?string
    {
        if (blank($this->woo_id)) {
            return null;
        }

        return $this->moloniAdminUrl('genInvoice', (int) $this->woo_id);
    }

    public function wooCommerceOrderReceivedUrl(): ?string
    {
        $url = rtrim((string) config('woocommerce.url'), '/');
        $orderKey = $this->raw_payload['order_key'] ?? null;

        if (blank($url) || blank($this->woo_id) || blank($orderKey)) {
            return null;
        }

        return $url.'/checkout/order-received/'.$this->woo_id.'/?'.http_build_query([
            'key' => $orderKey,
        ]);
    }

    public function publicInvoiceUrl(): ?string
    {
        if ($this->getKey() === null || $this->moloniDocumentId() === null) {
            return null;
        }

        return null; // pagina publica da fatura removida: B2C passou para o Moloni
    }

    private function moloniAdminUrl(string $action, int $id): ?string
    {
        $url = rtrim((string) config('woocommerce.url'), '/');

        if (blank($url) || $id <= 0) {
            return null;
        }

        return $url.'/wp-admin/admin.php?'.http_build_query([
            'page' => 'moloni',
            'action' => $action,
            'id' => $id,
        ]);
    }

    public function whatsappFaturaUrl(): ?string
    {
        $telefone = preg_replace('/\D+/', '', (string) $this->billing_phone);

        if (blank($telefone) || $this->moloniDocumentId() === null) {
            return null;
        }

        if (str_starts_with($telefone, '9')) {
            $telefone = '351'.$telefone;
        }

        $nome = $this->billing_name ?: ($this->prefersEnglish() ? 'there' : 'cliente');
        $invoiceUrl = $this->publicInvoiceUrl();

        if ($invoiceUrl === null) {
            return null;
        }

        $mensagem = $this->prefersEnglish()
            ? "Hi {$nome}! Your Horta da Maria invoice is available here: {$invoiceUrl} Thank you!"
            : "Ola {$nome}! A fatura da sua encomenda da Horta da Maria esta disponivel aqui: {$invoiceUrl} Obrigado!";

        return 'https://wa.me/'.$telefone.'?text='.rawurlencode($mensagem);
    }

    public function whatsappPagamentoUrl(): ?string
    {
        $telefone = preg_replace('/\D+/', '', (string) $this->billing_phone);
        $paymentUrl = $this->paymentUrl();

        if (blank($telefone) || blank($paymentUrl)) {
            return null;
        }

        if (str_starts_with($telefone, '9')) {
            $telefone = '351'.$telefone;
        }

        $nome = $this->billing_name ?: ($this->prefersEnglish() ? 'there' : 'cliente');
        $mensagem = $this->prefersEnglish()
            ? "Hi {$nome}! How are you? Your Horta da Maria order is ready. To complete it, you can pay through this link: {$paymentUrl} Thank you!"
            : "Ola {$nome}! Tudo bem? Ja deixamos a sua encomenda da Horta da Maria pronta. Para finalizar, pode fazer o pagamento por este link: {$paymentUrl} Obrigado!";

        return 'https://wa.me/'.$telefone.'?text='.rawurlencode($mensagem);
    }

    public function prefersEnglish(): bool
    {
        $language = $this->customerLanguage();

        return $language !== null && str_starts_with(strtolower($language), 'en');
    }

    private function customerLanguage(): ?string
    {
        if (filled($this->customer_language)) {
            return $this->customer_language;
        }

        foreach (['language', 'locale', 'customer_locale'] as $key) {
            $value = $this->raw_payload[$key] ?? null;

            if (filled($value)) {
                return (string) $value;
            }
        }

        foreach ($this->raw_payload['meta_data'] ?? [] as $item) {
            $key = strtolower((string) ($item['key'] ?? ''));

            if (in_array($key, ['trp_language', 'language', 'locale', '_locale', 'customer_locale'], true) && filled($item['value'] ?? null)) {
                return (string) $item['value'];
            }
        }

        return null;
    }
}
