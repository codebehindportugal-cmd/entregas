<?php

namespace App\Models;

use Database\Factories\WooOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        'line_items',
        'postponed_until',
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
        'ciclo_entrega',
        'scheduled_delivery_at',
        'raw_payload',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'line_items' => 'array',
            'excluded_products' => 'array',
            'raw_payload' => 'array',
            'postponed_until' => 'date',
            'next_payment_at' => 'date',
            'first_delivery_at' => 'date',
            'delivery_dates' => 'array',
            'cancelled_delivery_dates' => 'array',
            'subscription_ends_at' => 'date',
            'ordered_at' => 'datetime',
            'scheduled_delivery_at' => 'date',
            'synced_at' => 'datetime',
            'total' => 'decimal:2',
        ];
    }

    public function preparacaoItems(): HasMany
    {
        return $this->hasMany(PreparacaoItem::class);
    }

    public function entregasSubscricao(): array
    {
        $datas = $this->datasSubscricao();
        $canceladas = collect($this->cancelled_delivery_dates ?? [])
            ->filter()
            ->map(fn (string $data) => Carbon::parse($data)->toDateString())
            ->all();

        $preparadas = $this->preparacaoItems
            ->where('feito', true)
            ->map(fn (PreparacaoItem $item) => $item->data_preparacao->toDateString())
            ->all();

        $hoje = now()->toDateString();
        $postponedUntil = $this->postponed_until?->toDateString();
        $adiamentoJaAplicadoNoCalendario = $postponedUntil !== null && $datas->contains($postponedUntil);
        $dataAdiada = $postponedUntil === null || $adiamentoJaAplicadoNoCalendario
            ? null
            : $datas
                ->reject(fn (string $data) => in_array($data, $preparadas, true))
                ->reject(fn (string $data) => in_array($data, $canceladas, true))
                ->filter(fn (string $data) => $data < $postponedUntil)
                ->last();

        $feitas = $datas->filter(fn (string $data) => $data !== $dataAdiada && ! in_array($data, $canceladas, true) && ($data < $hoje || in_array($data, $preparadas, true)));
        $porRealizar = $datas->reject(fn (string $data) => $data !== $dataAdiada && ($data < $hoje || in_array($data, $preparadas, true) || in_array($data, $canceladas, true)));
        $proxima = $porRealizar->first(fn (string $data) => $data >= $hoje && ($postponedUntil === null || $data >= $postponedUntil));

        return [
            'total' => $datas->count(),
            'feitas' => $feitas->count(),
            'por_realizar' => $porRealizar->count(),
            'proxima' => $proxima ?? ($adiamentoJaAplicadoNoCalendario ? null : $postponedUntil),
        ];
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
        if ($this->next_payment_at !== null) {
            return $this->next_payment_at;
        }

        $fim = $this->fimCicloSubscricao();

        if ($fim === null) {
            return null;
        }

        return $fim->copy()->addWeeks($this->semanasPorCiclo());
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

        $dataOriginal ??= $datasPorAdiar->first();

        if ($dataOriginal === null) {
            $this->guardarAdiamento(['postponed_until' => $novaData]);

            return;
        }

        $novasDatas = $this->substituirDataDaSubscricao($datas, $dataOriginal, $novaData);
        $dataFim = collect($novasDatas)->last();

        $this->guardarAdiamento([
            'delivery_dates' => $novasDatas,
            'next_payment_at' => $this->next_payment_at?->toDateString(),
            'subscription_ends_at' => $dataFim,
            'postponed_until' => $novaData,
        ]);
    }

    private function substituirDataDaSubscricao(Collection $datas, string $dataOriginal, string $novaData): array
    {
        return $datas
            ->map(fn (string $data): string => $data === $dataOriginal ? $novaData : $data)
            ->sort()
            ->values()
            ->all();
    }

    private function datasBaseParaAdiamento(Collection $datas, string $novaData): Collection
    {
        if ($this->first_delivery_at === null || $novaData >= now()->toDateString()) {
            return $datas;
        }

        $data = $this->first_delivery_at->copy()->startOfDay();

        return collect(range(1, max(1, $datas->count())))
            ->map(function () use (&$data): string {
                $entrega = $data->toDateString();
                $data->addWeeks($this->semanasPorCiclo());

                return $entrega;
            });
    }

    private function guardarAdiamento(array $attributes): void
    {
        $this->forceFill($attributes);

        if ($this->exists) {
            $this->save();
        }
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

        $primeiraEntrega = $this->first_delivery_at->copy()->startOfDay();
        $limite = $this->subscription_ends_at?->copy()->addDay()->startOfDay()
            ?? $this->next_payment_at?->copy()->startOfDay()
            ?? $primeiraEntrega->copy()->addWeeks(4);

        if ($limite->lessThan($primeiraEntrega)) {
            $limite = $primeiraEntrega->copy();
        }

        $diaSemana = $this->diaSemanaSubscricao($primeiraEntrega);
        $datasGeradas = collect();
        $data = $primeiraEntrega->copy();

        while ($data->lessThan($limite)) {
            if ($data->dayOfWeek === $diaSemana) {
                $datasGeradas->push($data->toDateString());
                $data->addWeeks($this->semanasPorCiclo());

                continue;
            }

            $data->addDay();
        }

        return $datasGeradas->isNotEmpty()
            ? $datasGeradas
            : collect([$primeiraEntrega->toDateString()]);
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

    private function semanasPorCiclo(): int
    {
        return $this->ciclo_entrega === 'quinzenal' ? 2 : 1;
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

        $nome = $this->billing_name ?: 'cliente';
        $mensagem = "Olá {$nome}! Esperamos que tenha gostado da sua subscrição da Horta da Maria. A sua subscrição está a terminar e queríamos confirmar se pretende renovar para continuar a receber as suas entregas. Se quiser, podemos enviar-lhe já o link de renovação. Deseja que enviemos?";

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

        $nome = $this->billing_name ?: 'cliente';
        $mensagem = "Ola {$nome}! Tudo bem? Ja deixamos a sua encomenda da Horta da Maria pronta. Para finalizar, pode fazer o pagamento por este link: {$paymentUrl} Obrigado!";

        return 'https://wa.me/'.$telefone.'?text='.rawurlencode($mensagem);
    }
}
