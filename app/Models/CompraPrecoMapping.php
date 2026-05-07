<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraPrecoMapping extends Model
{
    protected $fillable = [
        'produto',
        'tabela_preco_item_id',
    ];

    public function tabelaPrecoItem(): BelongsTo
    {
        return $this->belongsTo(TabelaPrecoItem::class);
    }
}
