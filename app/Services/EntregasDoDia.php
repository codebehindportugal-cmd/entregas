<?php

namespace App\Services;

use App\Models\AtribuicaoEntrega;
use App\Models\Corporate;
use App\Models\User;
use App\Models\WooOrder;
use App\Models\Zona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * As entregas de um dia e quem as faz. As entregas estao atribuidas a zonas;
 * quem entrega e quem faz a zona nesse dia (horario da zona ou substituicao).
 */
class EntregasDoDia
{
    public const DIAS = Zona::DIAS;

    /**
     * Atribuicoes com zona que tem entrega nesta data, ja com a zona carregada,
     * pela ordem: zona e depois ordem da volta.
     */
    public function atribuicoes(Carbon $data): Collection
    {
        $this->garantirZonas($data);
        $dia = self::DIAS[$data->dayOfWeek] ?? null;

        return AtribuicaoEntrega::with(['corporate', 'wooOrder', 'zona.horarios', 'zona.substituicoes'])
            ->whereNotNull('zona_id')
            ->get()
            ->filter(fn (AtribuicaoEntrega $atribuicao): bool => $this->temEntregaNaData($atribuicao, $data, $dia))
            // Sem ordem definida, as paragens ficam agrupadas por codigo postal.
            ->sortBy(fn (AtribuicaoEntrega $atribuicao): string => sprintf(
                '%06d|%06d|%06d|%s',
                $atribuicao->zona?->ordem ?? 0,
                $atribuicao->zona_id,
                $atribuicao->ordem ?? 999999,
                $this->codigoPostal($atribuicao) ?? '9999',
            ))
            ->values();
    }

    /** As atribuicoes que calham a este colaborador nesta data. */
    public function doColaborador(User $colaborador, Carbon $data): Collection
    {
        return $this->atribuicoes($data)
            ->filter(fn (AtribuicaoEntrega $atribuicao): bool => $this->colaboradorId($atribuicao, $data) === $colaborador->id)
            ->values();
    }

    /** Quem entrega esta atribuicao nesta data (quem faz a zona). */
    public function colaboradorId(AtribuicaoEntrega $atribuicao, Carbon $data): ?int
    {
        return $atribuicao->zona?->colaboradorIdEm($data, $atribuicao->dia_semana);
    }

    public function temEntregaNaData(AtribuicaoEntrega $atribuicao, Carbon $data, ?string $dia = null): bool
    {
        $dia ??= self::DIAS[$data->dayOfWeek] ?? null;

        if ($atribuicao->tipo === 'b2c') {
            return $atribuicao->wooOrder !== null
                && $atribuicao->dia_semana === $dia
                && $atribuicao->wooOrder->temEntregaB2cNaData($data);
        }

        return $atribuicao->corporate !== null
            && $atribuicao->corporate->ativo
            && ! $atribuicao->corporate->parceiro_local
            && $atribuicao->corporate->diaEntregaOriginalParaData($data) === $atribuicao->dia_semana;
    }

    /**
     * Poe na zona do seu codigo postal todas as entregas deste dia que ainda
     * nao tem zona (encomendas B2C novas, renovacoes, empresas novas), para
     * ninguem ter de as atribuir a mao. O que o admin pos a mao nao se mexe;
     * o que foi posto automaticamente acompanha os codigos postais das zonas.
     * Atribuicoes de antes das zonas (com colaborador) ficam para a conversao.
     *
     * @return int quantas entregas ficaram com zona nova
     */
    public function garantirZonas(Carbon $data): int
    {
        $dia = self::DIAS[$data->dayOfWeek] ?? null;

        if ($dia === null) {
            return 0;
        }

        $zonas = Zona::where('ativo', true)->orderBy('ordem')->get();

        if ($zonas->isEmpty()) {
            return 0;
        }

        $entregas = Corporate::where('ativo', true)->where('parceiro_local', false)->get()
            ->map(fn (Corporate $corporate): ?array => ($diaOriginal = $corporate->diaEntregaOriginalParaData($data)) === null ? null : [
                'tipo' => 'corporate',
                'id' => $corporate->id,
                'dia' => $diaOriginal,
                'cp' => $this->codigoPostalDe($corporate->cp_entrega, $corporate->moradaParaEntrega(), $corporate->fatura_morada),
            ])
            ->filter()
            ->concat($this->encomendasB2c($dia, $data)->map(fn (WooOrder $order): array => [
                'tipo' => 'b2c',
                'id' => $order->id,
                'dia' => $dia,
                'cp' => $this->codigoPostalB2c($order),
            ]));

        if ($entregas->isEmpty()) {
            return 0;
        }

        $existentes = AtribuicaoEntrega::query()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('tipo', 'corporate')->whereIn('corporate_id', $entregas->where('tipo', 'corporate')->pluck('id')))
                ->orWhere(fn ($query) => $query->where('tipo', 'b2c')->whereIn('woo_order_id', $entregas->where('tipo', 'b2c')->pluck('id'))))
            ->get()
            ->keyBy(fn (AtribuicaoEntrega $a): string => ($a->tipo === 'b2c' ? 'b'.$a->woo_order_id : 'c'.$a->corporate_id).'|'.$a->dia_semana);

        $mudadas = 0;

        foreach ($entregas as $entrega) {
            $atribuicao = $existentes->get(($entrega['tipo'] === 'b2c' ? 'b' : 'c').$entrega['id'].'|'.$entrega['dia']);
            $zona = Zona::sugeridaPara($entrega['cp'], $zonas);

            if ($atribuicao === null) {
                if ($zona === null) {
                    continue;
                }

                AtribuicaoEntrega::create([
                    'tipo' => $entrega['tipo'],
                    'corporate_id' => $entrega['tipo'] === 'corporate' ? $entrega['id'] : null,
                    'woo_order_id' => $entrega['tipo'] === 'b2c' ? $entrega['id'] : null,
                    'dia_semana' => $entrega['dia'],
                    'zona_id' => $zona->id,
                    'zona_automatica' => true,
                ]);
                $mudadas++;

                continue;
            }

            // Posta a mao, ou ainda por converter: nao se mexe.
            if (! $atribuicao->zona_automatica || ($atribuicao->zona_id === null && $atribuicao->user_id !== null)) {
                continue;
            }

            if ($zona !== null && (int) $atribuicao->zona_id !== $zona->id) {
                $atribuicao->update(['zona_id' => $zona->id, 'ordem' => null]);
                $mudadas++;
            }
        }

        return $mudadas;
    }

    private function codigoPostal(AtribuicaoEntrega $atribuicao): ?string
    {
        return $atribuicao->tipo === 'b2c'
            ? ($atribuicao->wooOrder ? $this->codigoPostalB2c($atribuicao->wooOrder) : null)
            : $this->codigoPostalDe($atribuicao->corporate?->cp_entrega, $atribuicao->corporate?->moradaParaEntrega(), $atribuicao->corporate?->fatura_morada);
    }

    private function codigoPostalB2c(WooOrder $order): ?string
    {
        $shipping = (array) ($order->raw_payload['shipping'] ?? []);
        $billing = (array) ($order->raw_payload['billing'] ?? []);

        return $this->codigoPostalDe($shipping['postcode'] ?? null, $billing['postcode'] ?? null);
    }

    /** O primeiro codigo postal portugues (0000-000) que aparecer nestes textos. */
    private function codigoPostalDe(?string ...$textos): ?string
    {
        foreach ($textos as $texto) {
            if (preg_match('/\b(\d{4})\s?-\s?(\d{3})\b/', (string) $texto, $m)) {
                return $m[1].'-'.$m[2];
            }

            if (preg_match('/^\s*(\d{4})\s*$/', (string) $texto, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    /** As encomendas B2C com entrega neste dia (segunda, quarta ou sabado). */
    public function encomendasB2c(string $dia, Carbon $dataSelecionada, string $q = ''): Collection
    {
        $data = $dataSelecionada->toDateString();
        $diaB2c = match ($dia) {
            'Segunda' => 'segunda',
            'Quarta' => 'quarta',
            'Sabado' => 'sabado',
            default => null,
        };

        if ($diaB2c === null) {
            return collect();
        }

        return WooOrder::query()
            ->where(function ($query): void {
                $query->whereIn('status', ['processing', 'on-hold', 'pending'])
                    ->orWhereIn('status', ['subscricao', 'wc-subscricao', 'active'])
                    ->orWhere('source_type', 'subscription');
            })
            ->where(function ($query) use ($diaB2c, $data): void {
                $query->whereDate('postponed_until', $data)
                    ->orWhere(function ($query) use ($diaB2c, $data): void {
                        $query->where(function ($query) use ($data): void {
                            $query->whereNull('postponed_until')
                                ->orWhereDate('postponed_until', '<', $data);
                        })->where(function ($query) use ($diaB2c, $data): void {
                            $query->whereJsonContains('delivery_dates', $data)
                                ->orWhereDate('scheduled_delivery_at', $data)
                                ->orWhere(function ($query) use ($diaB2c): void {
                                    $query->where('source_type', 'order')
                                        ->where('status', '!=', 'subscricao')
                                        ->where('dia_entrega', $diaB2c)
                                        ->whereNull('scheduled_delivery_at');
                                })
                                ->orWhere(function ($query) use ($diaB2c): void {
                                    $query->where(function ($query): void {
                                        $query->whereNull('delivery_dates')
                                            ->orWhereJsonLength('delivery_dates', 0);
                                    })->whereNull('scheduled_delivery_at')
                                        ->where('dia_entrega', $diaB2c);
                                });
                        });
                    });
            })
            ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                $query->where('billing_name', 'like', "%{$q}%")
                    ->orWhere('billing_phone', 'like', "%{$q}%")
                    ->orWhere('billing_email', 'like', "%{$q}%")
                    ->orWhere('woo_id', 'like', "%{$q}%");
            }))
            ->orderBy('billing_name')
            ->get()
            ->filter(fn (WooOrder $order): bool => $order->temEntregaB2cNaData($dataSelecionada))
            ->values();
    }
}
