<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooProduct extends Model
{
    protected $fillable = [
        'woo_id',
        'name',
        'slug',
        'sku',
        'type',
        'status',
        'permalink',
        'image_url',
        'price',
        'regular_price',
        'sale_price',
        'stock_status',
        'purchasable',
        'em_epoca',
        'disponivel_compra',
        'epoca',
        'tabela_preco_item_id',
        'custo_quantidade',
        'custo_unidade',
        'categories',
        'raw_payload',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'regular_price' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'purchasable' => 'boolean',
            'em_epoca' => 'boolean',
            'disponivel_compra' => 'boolean',
            'custo_quantidade' => 'decimal:4',
            'categories' => 'array',
            'raw_payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function tabelaPrecoItem(): BelongsTo
    {
        return $this->belongsTo(TabelaPrecoItem::class);
    }

    public function precoVenda(): ?float
    {
        if ($this->sale_price !== null && (float) $this->sale_price > 0) {
            return (float) $this->sale_price;
        }

        if ($this->regular_price !== null && (float) $this->regular_price > 0) {
            return (float) $this->regular_price;
        }

        return $this->price !== null ? (float) $this->price : null;
    }

    public function custoCompra(): ?float
    {
        if ($this->tabelaPrecoItem === null || $this->tabelaPrecoItem->preco_kg === null) {
            return null;
        }

        return round((float) $this->custo_quantidade * (float) $this->tabelaPrecoItem->preco_kg, 4);
    }

    public function margem(): ?float
    {
        $venda = $this->precoVenda();
        $custo = $this->custoCompra();

        return $venda !== null && $custo !== null ? round($venda - $custo, 4) : null;
    }

    public function margemPercentagem(): ?float
    {
        $venda = $this->precoVenda();
        $margem = $this->margem();

        return $venda !== null && $venda > 0 && $margem !== null ? round(($margem / $venda) * 100, 1) : null;
    }
}
