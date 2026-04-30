<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistoEntrega extends Model
{
    /** @use HasFactory<\Database\Factories\RegistoEntregaFactory> */
    use HasFactory;

    protected $table = 'registo_entregas';

    protected $fillable = [
        'corporate_id',
        'user_id',
        'data_entrega',
        'status',
        'hora_entrega',
        'nota',
        'fotos',
    ];

    protected function casts(): array
    {
        return [
            'data_entrega' => 'date',
            'hora_entrega' => 'datetime:H:i',
            'fotos' => 'array',
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
