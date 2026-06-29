<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DespesaFoto extends Model
{
    protected $fillable = ['despesa_id', 'path', 'ordem'];

    public function despesa(): BelongsTo
    {
        return $this->belongsTo(Despesa::class);
    }

    public function getUrlAttribute(): string
    {
        return route('public-files.show', ['path' => $this->path]);
    }
}
