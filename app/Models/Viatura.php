<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Um carro da frota. A matricula daqui e a que vai para a guia de transporte
 * do Moloni (vehicle_name / vehicle_number_plate).
 */
class Viatura extends Model
{
    protected $table = 'viaturas';

    protected $fillable = [
        'matricula',
        'nome',
        'ativo',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    public function despesas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Despesa::class);
    }

    /** "12-ab 34" e "12AB34" sao o mesmo carro: so letras e numeros, em maiusculas. */
    public static function normalizarMatricula(?string $matricula): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $matricula));
    }

    /** A viatura com esta matricula, escrita de qualquer maneira. */
    public static function daMatricula(?string $matricula): ?self
    {
        $alvo = self::normalizarMatricula($matricula);

        if ($alvo === '') {
            return null;
        }

        return self::query()->get()->first(fn (self $v) => self::normalizarMatricula($v->matricula) === $alvo);
    }

    public function scopeAtiva(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /** "12-AB-34 — Carrinha branca" ou so a matricula. */
    public function etiqueta(): string
    {
        return filled($this->nome)
            ? $this->matricula.' - '.$this->nome
            : $this->matricula;
    }

    /**
     * As viaturas que devem aparecer na select box da preparacao. Inclui
     * sempre a matricula que ja estava guardada na linha, mesmo que o carro
     * entretanto tenha sido desativado — senao a linha perdia o valor.
     *
     * @return \Illuminate\Support\Collection<int, Viatura>
     */
    public static function paraEscolha(?string $matriculaAtual = null): \Illuminate\Support\Collection
    {
        $viaturas = static::query()
            ->ativa()
            ->orderBy('ordem')
            ->orderBy('matricula')
            ->get();

        $matriculaAtual = trim((string) $matriculaAtual);

        if ($matriculaAtual !== '' && $viaturas->doesntContain('matricula', $matriculaAtual)) {
            $viaturas->push(new static(['matricula' => $matriculaAtual]));
        }

        return $viaturas;
    }
}
