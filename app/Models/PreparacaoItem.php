<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreparacaoItem extends Model
{
    /** @use HasFactory<\Database\Factories\PreparacaoItemFactory> */
    use HasFactory;

    protected $fillable = [
        'data_preparacao',
        'tipo',
        'corporate_id',
        'woo_order_id',
        'feito',
        'feito_at',
        'feito_por',
        'produtos_picados',
    ];

    protected function casts(): array
    {
        return [
            'data_preparacao' => 'date',
            'feito' => 'boolean',
            'feito_at' => 'datetime',
            'produtos_picados' => 'array',
        ];
    }

    public function corporate(): BelongsTo
    {
        return $this->belongsTo(Corporate::class);
    }

    public function wooOrder(): BelongsTo
    {
        return $this->belongsTo(WooOrder::class);
    }

    public function feitoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feito_por');
    }
}
