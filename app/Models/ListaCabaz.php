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
