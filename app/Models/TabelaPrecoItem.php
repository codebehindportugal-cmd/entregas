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
        'epoca',
        'em_epoca',
        'disponivel_compra',
        'woo_product_id',
        'woo_variation_id',
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
            'em_epoca' => 'boolean',
            'disponivel_compra' => 'boolean',
            'woo_product_id' => 'integer',
            'woo_variation_id' => 'integer',
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
