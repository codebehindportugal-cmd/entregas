<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateHistorico extends Model
{
    /** @use HasFactory<\Database\Factories\CorporateHistoricoFactory> */
    use HasFactory;

    protected $fillable = [
        'corporate_id',
        'user_id',
        'data',
        'texto',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
        ];
    }

    public function corporate(): BelongsTo
    {
        return $this->belongsTo(Corporate::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
