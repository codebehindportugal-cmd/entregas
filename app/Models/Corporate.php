<?php

namespace App\Models;

use Database\Factories\CorporateFactory;
use App\Services\HolidayCalendarService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Corporate extends Model
{
    /** @use HasFactory<CorporateFactory> */
    use HasFactory;

    private const FRUTAS = ['banana', 'maca', 'pera', 'laranja', 'kiwi', 'uvas', 'fruta_epoca', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

    private const PASTELARIA = ['pao_mistura', 'pao_forma', 'croissant', 'bolo'];

    private const PRODUTOS_KG = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

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
        'preco_venda_peca',
        'cabaz_tipo',
        'cabaz_quantidade',
        'peso_total',
        'frutas',
        'frutas_por_dia',
        'pastelaria',
        'pastelaria_por_dia',
        'produtos_mensais',
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
            'pastelaria' => 'array',
            'pastelaria_por_dia' => 'array',
            'produtos_mensais' => 'array',
            'ativo' => 'boolean',
            'peso_total' => 'decimal:2',
            'preco_venda_peca' => 'decimal:4',
            'cabaz_quantidade' => 'integer',
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

    public function configSnapshots(): HasMany
    {
        return $this->hasMany(CorporateConfigSnapshot::class);
    }

    public function snapshotDados(): array
    {
        return [
            'dias_entrega' => $this->dias_entrega ?? [],
            'periodicidade_entrega' => $this->periodicidade_entrega ?? 'semanal',
            'quinzenal_referencia' => $this->quinzenal_referencia?->toDateString(),
            'pecas_por_dia' => $this->pecasPorDiaEntrega(),
            'produtos_kg_por_dia' => $this->produtosKgPorDiaEntrega(),
            'pastelaria_por_dia' => $this->pastelariaPorDiaEntrega(),
            'produtos_mensais' => $this->produtos_mensais ?? [],
        ];
    }

    public function frutasParaDia(string $dia): array
    {
        $frutasBase = $this->frutas ?? [];
        $frutasDoDia = $this->frutas_por_dia[$dia] ?? [];
        $temFrutasDoDia = is_array($this->frutas_por_dia ?? null) && array_key_exists($dia, $this->frutas_por_dia);

        return collect(self::FRUTAS)
            ->mapWithKeys(function (string $fruta) use ($frutasBase, $frutasDoDia, $temFrutasDoDia): array {
                $value = $temFrutasDoDia ? ($frutasDoDia[$fruta] ?? 0) : ($frutasBase[$fruta] ?? 0);

                return [$fruta => in_array($fruta, self::PRODUTOS_KG, true) ? round((float) $value, 2) : (int) $value];
            })
            ->all();
    }

    public function totalPecasParaDia(string $dia): int
    {
        return (int) array_sum(collect($this->frutasParaDia($dia))->except(self::PRODUTOS_KG)->all());
    }

    public function pastelariaPorDia(string $dia): array
    {
        $pastelariaBase = $this->pastelaria ?? [];
        $pastelariaDoDia = $this->pastelaria_por_dia[$dia] ?? [];
        $temPastelariaDoDia = is_array($this->pastelaria_por_dia ?? null) && array_key_exists($dia, $this->pastelaria_por_dia);

        return collect(self::PASTELARIA)
            ->mapWithKeys(function (string $produto) use ($pastelariaBase, $pastelariaDoDia, $temPastelariaDoDia): array {
                $value = $temPastelariaDoDia ? ($pastelariaDoDia[$produto] ?? 0) : ($pastelariaBase[$produto] ?? 0);

                return [$produto => (int) $value];
            })
            ->all();
    }

    public function totalPastelariaPorDia(string $dia): int
    {
        return (int) array_sum($this->pastelariaPorDia($dia));
    }

    public function produtosKgPorDiaEntrega(): array
    {
        return collect($this->dias_entrega ?? [])
            ->mapWithKeys(fn (string $dia): array => [
                $dia => collect($this->frutasParaDia($dia))
                    ->only(self::PRODUTOS_KG)
                    ->map(fn (int|float $quantidade): float => round((float) $quantidade, 2))
                    ->all(),
            ])
            ->all();
    }

    public function pastelariaPorDiaEntrega(): array
    {
        return collect($this->dias_entrega ?? [])
            ->mapWithKeys(fn (string $dia): array => [$dia => $this->pastelariaPorDia($dia)])
            ->all();
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

    public function valorVendaParaDia(string $dia): ?float
    {
        if ($this->preco_venda_peca === null) {
            return null;
        }

        return round($this->totalPecasParaDia($dia) * (float) $this->preco_venda_peca, 2);
    }

    public function valorVendaPorSemana(): ?float
    {
        if ($this->preco_venda_peca === null) {
            return null;
        }

        return round($this->totalPecasPorSemana() * (float) $this->preco_venda_peca, 2);
    }

    public function temEntregaNaData(\DateTimeInterface $data): bool
    {
        $data = Carbon::parse($data)->startOfDay();
        $holidayCalendar = app(HolidayCalendarService::class);

        if ($holidayCalendar->isHolidayForCorporate($data, $this)) {
            return false;
        }

        if ($this->temEntregaRegularNaData($data)) {
            return true;
        }

        return $this->diaEntregaOriginalParaData($data) !== null;
    }

    public function diaEntregaOriginalParaData(\DateTimeInterface $data): ?string
    {
        $data = Carbon::parse($data)->startOfDay();

        if ($this->temEntregaRegularNaData($data)) {
            return $this->diaSemana($data);
        }

        if ($this->entregaTodosOsDiasUteis()) {
            return null;
        }

        $holidayCalendar = app(HolidayCalendarService::class);
        $cursor = $data->copy()->subDay();

        while ($cursor->greaterThanOrEqualTo($data->copy()->subDays(14))) {
            $diaOriginal = $this->diaSemana($cursor);

            if (
                $diaOriginal !== null
                && $this->temEntregaRegularNaData($cursor)
                && $holidayCalendar->isHolidayForCorporate($cursor, $this)
                && $this->proximoDiaEntregaDepoisDoFeriado($cursor)?->isSameDay($data)
            ) {
                return $diaOriginal;
            }

            $cursor->subDay();
        }

        return null;
    }

    private function temEntregaRegularNaData(Carbon $data): bool
    {
        $diaSemana = $this->diaSemana($data);

        if ($diaSemana === null || ! in_array($diaSemana, $this->dias_entrega ?? [], true)) {
            return false;
        }

        if ($this->periodicidade_entrega !== 'quinzenal' || $this->quinzenal_referencia === null) {
            return true;
        }

        $semanaReferencia = $this->quinzenal_referencia->copy()->startOfWeek();
        $semanaDaData = $data->copy()->startOfWeek();

        return ((int) $semanaReferencia->diffInWeeks($semanaDaData)) % 2 === 0;
    }

    private function proximoDiaEntregaDepoisDoFeriado(Carbon $feriado): ?Carbon
    {
        $holidayCalendar = app(HolidayCalendarService::class);
        $data = $feriado->copy()->addDay();

        while ($data->lessThanOrEqualTo($feriado->copy()->addDays(31))) {
            if ($this->temEntregaRegularNaData($data) && ! $holidayCalendar->isHolidayForCorporate($data, $this)) {
                return $data;
            }

            $data->addDay();
        }

        return null;
    }

    private function entregaTodosOsDiasUteis(): bool
    {
        $dias = $this->dias_entrega ?? [];

        return collect(['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta'])
            ->every(fn (string $dia): bool => in_array($dia, $dias, true));
    }

    private function diaSemana(Carbon $data): ?string
    {
        return [
            1 => 'Segunda',
            2 => 'Terca',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sabado',
        ][$data->dayOfWeek] ?? null;
    }

    public function usaCabazTipo(): bool
    {
        return filled($this->cabaz_tipo);
    }
}
