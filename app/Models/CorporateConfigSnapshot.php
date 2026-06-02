<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorporateConfigSnapshot extends Model
{
    protected $fillable = [
        'corporate_id',
        'effective_from',
        'dados',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'dados' => 'array',
        ];
    }

    public function corporate(): BelongsTo
    {
        return $this->belongsTo(Corporate::class);
    }
}
