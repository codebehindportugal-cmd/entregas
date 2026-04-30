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
        $dataAdiada = $postponedUntil === null
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
            'proxima' => $proxima ?? $postponedUntil,
        ];
    }

    private function datasSubscricao(): Collection
    {
        $datas = collect($this->delivery_dates ?? [])
            ->filter()
            ->map(fn (string $data) => Carbon::parse($data)->toDateString())
            ->unique()
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
}
