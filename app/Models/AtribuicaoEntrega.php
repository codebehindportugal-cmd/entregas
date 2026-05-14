<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtribuicaoEntrega extends Model
{
    /** @use HasFactory<\Database\Factories\AtribuicaoEntregaFactory> */
    use HasFactory;

    protected $table = 'atribuicoes';

    protected $fillable = [
        'tipo',
        'corporate_id',
        'woo_order_id',
        'user_id',
        'dia_semana',
    ];

    public function corporate(): BelongsTo
    {
        return $this->belongsTo(Corporate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wooOrder(): BelongsTo
    {
        return $this->belongsTo(WooOrder::class);
    }
}
