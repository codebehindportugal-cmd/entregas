<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TabelaPreco extends Model
{
    protected $table = 'tabelas_precos';

    protected $fillable = [
        'fornecedor',
        'descricao',
        'valida_de',
        'valida_ate',
        'ativa',
    ];

    protected function casts(): array
    {
        return [
            'valida_de' => 'date',
            'valida_ate' => 'date',
            'ativa' => 'boolean',
        ];
    }

    public function itens(): HasMany
    {
        return $this->hasMany(TabelaPrecoItem::class);
    }

    public function scopeAtiva(Builder $query): Builder
    {
        return $query->where('ativa', true)->orderByDesc('valida_de');
    }

    public function tituloFormatado(): string
    {
        return "{$this->fornecedor} - {$this->descricao} ({$this->valida_de->format('d/m/Y')})";
    }

    public static function ativaParaData(Carbon $data): ?self
    {
        return self::query()
            ->where('ativa', true)
            ->whereDate('valida_de', '<=', $data)
            ->where(fn ($query) => $query->whereNull('valida_ate')->orWhereDate('valida_ate', '>=', $data))
            ->orderByDesc('valida_de')
            ->first();
    }
}
