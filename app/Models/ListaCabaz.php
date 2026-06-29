<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ListaCabaz extends Model
{
    protected $table = 'lista_cabazes';

    protected $fillable = [
        'semana_numero',
        'ano',
        'mes',
        'descricao',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'semana_numero' => 'integer',
            'ano' => 'integer',
            'mes' => 'integer',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ListaCabazItem::class);
    }

    public function scopePublicada(Builder $query): Builder
    {
        return $query->where('estado', 'publicada');
    }

    public function tituloFormatado(): string
    {
        return $this->descricao ?: 'Semana '.$this->semana_numero.' - '.$this->meses()[$this->mes].' '.$this->ano;
    }

    public function itensPorTipo(string $tipo)
    {
        return $this->itens()
            ->where('cabaz_tipo', $tipo)
            ->orderBy('categoria')
            ->orderBy('ordem')
            ->get();
    }

    /** Preço mensal por tipo de cabaz (subscrições B2C). */
    public static function precosMensais(): array
    {
        return [
            'mini'    => 60.00,
            'pequeno' => 125.00,
            'medio'   => 175.00,
            'grande'  => 215.00,
        ];
    }

    /** Preço por entrega semanal (mensal ÷ 4). */
    public static function precosPorCabaz(): array
    {
        return array_map(fn (float $p): float => round($p / 4, 2), self::precosMensais());
    }

    /** Custo máximo por cabaz para atingir 60 % de margem. */
    public static function custoMaxPorCabaz(): array
    {
        return array_map(fn (float $p): float => round($p * 0.40, 2), self::precosPorCabaz());
    }

    public static function meses(): array
    {
        return [
            1 => 'Janeiro',
            2 => 'Fevereiro',
            3 => 'Marco',
            4 => 'Abril',
            5 => 'Maio',
            6 => 'Junho',
            7 => 'Julho',
            8 => 'Agosto',
            9 => 'Setembro',
            10 => 'Outubro',
            11 => 'Novembro',
            12 => 'Dezembro',
        ];
    }
}
