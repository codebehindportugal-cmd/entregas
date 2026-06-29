<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class AiJob extends Model
{
    protected $fillable = [
        'despesa_id',
        'status',
        'image_path',
        'result',
    ];

    public function despesa(): BelongsTo
    {
        return $this->belongsTo(Despesa::class);
    }

    public function getImageUrlAttribute(): string
    {
        return URL::temporarySignedRoute('ai-jobs.image', now()->addHours(6), ['job' => $this]);
    }
}
