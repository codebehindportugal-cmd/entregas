<?php

namespace App\Models;

use Database\Factories\CorporateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Corporate extends Model
{
    /** @use HasFactory<CorporateFactory> */
    use HasFactory;

    private const FRUTAS = ['banana', 'maca', 'pera', 'laranja', 'kiwi', 'uvas', 'fruta_epoca'];

    protected $fillable = [
        'empresa',
        'sucursal',
        'morada_entrega',
        'dias_entrega',
        'periodicidade_entrega',
        'quinzenal_referencia',
        'horario_entrega',
        'responsavel_nome',
        'responsavel_telefone',
        'fatura_nome',
        'fatura_nif',
        'fatura_email',
        'fatura_morada',
        'numero_caixas',
        'peso_total',
        'frutas',
        'frutas_por_dia',
        'notas',
        'ativo',
    ];

    protected function casts(): array
    {
        return [
            'dias_entrega' => 'array',
            'quinzenal_referencia' => 'date',
            'frutas' => 'array',
            'frutas_por_dia' => 'array',
            'ativo' => 'boolean',
            'peso_total' => 'decimal:2',
        ];
    }

    public function atribuicoes(): HasMany
    {
        return $this->hasMany(AtribuicaoEntrega::class);
    }

    public function registosEntrega(): HasMany
    {
        return $this->hasMany(RegistoEntrega::class);
    }

    public function moradaParaEntrega(): ?string
    {
        return $this->morada_entrega ?: $this->fatura_morada;
    }

    public function googleMapsUrl(): ?string
    {
        if (! $this->moradaParaEntrega()) {
            return null;
        }

        return 'https://www.google.com/maps/dir/?api=1&destination='.rawurlencode($this->moradaParaEntrega());
    }

    public function wazeUrl(): ?string
    {
        if (! $this->moradaParaEntrega()) {
            return null;
        }

        return 'https://waze.com/ul?q='.rawurlencode($this->moradaParaEntrega()).'&navigate=yes';
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(CorporateHistorico::class);
    }

    public function frutasParaDia(string $dia): array
    {
        $frutasBase = $this->frutas ?? [];
        $frutasDoDia = $this->frutas_por_dia[$dia] ?? [];
        $temFrutasDoDia = is_array($this->frutas_por_dia ?? null) && array_key_exists($dia, $this->frutas_por_dia);

        return collect(self::FRUTAS)
            ->mapWithKeys(function (string $fruta) use ($frutasBase, $frutasDoDia, $temFrutasDoDia): array {
                $value = $temFrutasDoDia ? ($frutasDoDia[$fruta] ?? 0) : ($frutasBase[$fruta] ?? 0);

                return [$fruta => $fruta === 'uvas' ? round((float) $value, 2) : (int) $value];
            })
            ->all();
    }

    public function totalPecasParaDia(string $dia): int
    {
        return (int) array_sum(collect($this->frutasParaDia($dia))->except('uvas')->all());
    }

    public function pecasPorDiaEntrega(): array
    {
        return collect($this->dias_entrega ?? [])
            ->mapWithKeys(fn (string $dia) => [$dia => $this->totalPecasParaDia($dia)])
            ->all();
    }

    public function totalPecasPorSemana(): int
    {
        return (int) round((float) $this->peso_total);
    }

    public function temEntregaNaData(\DateTimeInterface $data): bool
    {
        if ($this->periodicidade_entrega !== 'quinzenal' || $this->quinzenal_referencia === null) {
            return true;
        }

        $semanaReferencia = $this->quinzenal_referencia->copy()->startOfWeek();
        $semanaDaData = Carbon::parse($data)->startOfWeek();

        return ((int) $semanaReferencia->diffInWeeks($semanaDaData)) % 2 === 0;
    }
}
