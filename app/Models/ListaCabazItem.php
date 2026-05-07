<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListaCabazItem extends Model
{
    protected $table = 'lista_cabaz_itens';

    protected $fillable = [
        'lista_cabaz_id',
        'cabaz_tipo',
        'produto',
        'categoria',
        'quantidade',
        'unidade',
        'tabela_preco_item_id',
        'preco_unitario',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'preco_unitario' => 'decimal:4',
        ];
    }

    public function listaCabaz(): BelongsTo
    {
        return $this->belongsTo(ListaCabaz::class);
    }

    public function tabelaPrecoItem(): BelongsTo
    {
        return $this->belongsTo(TabelaPrecoItem::class);
    }

    public function precoEfetivo(): ?float
    {
        if ($this->tabelaPrecoItem) {
            return (float) $this->tabelaPrecoItem->preco_kg;
        }

        return $this->preco_unitario ? (float) $this->preco_unitario : null;
    }

    public function custoUnitario(): ?float
    {
        $preco = $this->precoEfetivo();

        return $preco !== null ? round((float) $this->quantidade * $preco, 4) : null;
    }
}
