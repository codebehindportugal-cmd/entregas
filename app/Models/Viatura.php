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
