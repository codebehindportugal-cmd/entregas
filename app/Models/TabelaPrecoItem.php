<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TabelaPrecoItem extends Model
{
    protected $table = 'tabela_preco_itens';

    protected $fillable = [
        'tabela_preco_id',
        'categoria',
        'produto',
        'origem',
        'calibre',
        'preco_kg',
        'preco_kg_iva',
        'unidade',
        'notas',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'preco_kg' => 'decimal:4',
            'preco_kg_iva' => 'decimal:4',
        ];
    }

    public function tabelaPreco(): BelongsTo
    {
        return $this->belongsTo(TabelaPreco::class);
    }

    public function scopePorCategoria(Builder $query, string $categoria): Builder
    {
        return $query->where('categoria', $categoria);
    }

    public function precoFormatado(): string
    {
        return number_format((float) $this->preco_kg, 2, ',', ' ').' €/'.($this->unidade ?: 'kg');
    }
}
