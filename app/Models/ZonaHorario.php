<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZonaHorario extends Model
{
    protected $table = 'zona_horarios';

    protected $fillable = ['zona_id', 'dia_semana', 'user_id', 'acompanha_zona_id'];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class);
    }

    public function acompanha(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'acompanha_zona_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
