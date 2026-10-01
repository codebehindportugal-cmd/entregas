<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiToken extends Model
{
    protected $fillable = ['user_id', 'nome', 'token_hash', 'ultimo_uso_em'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['ultimo_uso_em' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
