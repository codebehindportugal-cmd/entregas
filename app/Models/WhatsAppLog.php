<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppLog extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsAppLogFactory> */
    use HasFactory;

    protected $table = 'whats_app_logs';

    protected $fillable = [
        'to',
        'message',
        'status',
        'response',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
