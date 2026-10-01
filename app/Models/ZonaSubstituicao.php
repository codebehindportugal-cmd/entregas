<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZonaSubstituicao extends Model
{
    protected $table = 'zona_substituicoes';

    protected $fillable = ['zona_id', 'inicio', 'fim', 'user_id', 'nota'];

    protected $casts = [
        'inicio' => 'date',
        'fim' => 'date',
    ];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
