<?php

namespace App\Services;

use App\Models\Corporate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class HolidayCalendarService
{
    private const NATIONAL_FIXED = [
        '01-01' => 'Ano Novo',
        '04-25' => 'Dia da Liberdade',
        '05-01' => 'Dia do Trabalhador',
        '06-10' => 'Dia de Portugal',
        '08-15' => 'Assuncao de Nossa Senhora',
        '10-05' => 'Implantacao da Republica',
        '11-01' => 'Todos os Santos',
        '12-01' => 'Restauracao da Independencia',
        '12-08' => 'Imaculada Conceicao',
        '12-25' => 'Natal',
    ];

    private const MUNICIPAL_FIXED = [
        'alcochete' => ['06-24' => 'Sao Joao'],
        'aveiro' => ['05-12' => 'Santa Joana Princesa'],
        'evora' => ['06-29' => 'Sao Pedro'],
        'faro' => ['09-07' => 'Dia do Municipio'],
        'leiria' => ['05-22' => 'Dia do Municipio'],
        'lisboa' => ['06-13' => 'Santo Antonio'],
        'porto' => ['06-24' => 'Sao Joao'],
        'vila real' => ['06-13' => 'Santo Antonio'],
        'viseu' => ['09-21' => 'Sao Mateus'],
    ];

    public function isHolidayForCorporate(Carbon|string $date, ?Corporate $corporate = null): bool
    {
        return $this->holidayForCorporate($date, $corporate) !== null;
    }

    public function holidayForCorporate(Carbon|string $date, ?Corporate $corporate = null): ?array
    {
        $date = Carbon::parse($date)->startOfDay();
        $key = $date->toDateString();

        return $this->holidaysForYear($date->year, $corporate)->firstWhere('date', $key);
    }

    public function upcoming(int $months = 12, ?Corporate $corporate = null): Collection
    {
        $start = now()->startOfMonth();
        $end = now()->copy()->addMonthsNoOverflow($months)->endOfMonth();

        return collect(range($start->year, $end->year))
            ->flatMap(fn (int $year): Collection => $this->holidaysForYear($year, $corporate))
            ->filter(fn (array $holiday): bool => $holiday['date'] >= $start->toDateString() && $holiday['date'] <= $end->toDateString())
            ->sortBy('date')
            ->values();
    }

    public function holidaysForYear(int $year, ?Corporate $corporate = null): Collection
    {
        $holidays = collect(self::NATIONAL_FIXED)
            ->map(fn (string $name, string $day): array => $this->holiday($year, $day, $name, 'nacional'));

        $easter = Carbon::createFromTimestamp(easter_date($year))->startOfDay();
        $holidays = $holidays
            ->push([
                'date' => $easter->copy()->subDays(2)->toDateString(),
                'name' => 'Sexta-feira Santa',
                'type' => 'nacional',
                'municipality' => null,
            ])
            ->push([
                'date' => $easter->toDateString(),
                'name' => 'Pascoa',
                'type' => 'nacional',
                'municipality' => null,
            ])
            ->push([
                'date' => $easter->copy()->addDays(60)->toDateString(),
                'name' => 'Corpo de Deus',
                'type' => 'nacional',
                'municipality' => null,
            ]);

        $municipality = $corporate === null ? null : $this->municipalityForCorporate($corporate);

        if ($municipality !== null) {
            foreach (self::MUNICIPAL_FIXED[$municipality] ?? [] as $day => $name) {
                $holidays->push($this->holiday($year, $day, $name, 'municipal', Str::title($municipality)));
            }
        } elseif ($corporate === null) {
            foreach (self::MUNICIPAL_FIXED as $municipalityKey => $days) {
                foreach ($days as $day => $name) {
                    $holidays->push($this->holiday($year, $day, $name, 'municipal', Str::title($municipalityKey)));
                }
            }
        }

        return $holidays
            ->unique(fn (array $holiday): string => implode('|', [$holiday['date'], $holiday['type'], $holiday['municipality'] ?? '']))
            ->sortBy('date')
            ->values();
    }

    public function municipalityForCorporate(Corporate $corporate): ?string
    {
        $text = Str::of(collect([
            $corporate->sucursal,
            $corporate->morada_entrega,
            $corporate->fatura_morada,
            $corporate->notas,
        ])->filter()->implode(' '))->lower()->ascii()->toString();

        foreach (array_keys(self::MUNICIPAL_FIXED) as $municipality) {
            if (str_contains($text, Str::ascii($municipality))) {
                return $municipality;
            }
        }

        return null;
    }

    private function holiday(int $year, string $day, string $name, string $type, ?string $municipality = null): array
    {
        return [
            'date' => Carbon::createFromFormat('Y-m-d', "{$year}-{$day}")->toDateString(),
            'name' => $name,
            'type' => $type,
            'municipality' => $municipality,
        ];
    }
}
