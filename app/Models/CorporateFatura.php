<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateFatura extends Model
{
    protected $fillable = [
        'nif',
        'nome',
        'periodo',
        'ciclo_ref',
        'ciclo_label',
        'referencia_cliente',
        'document_id',
        'tipo',
        'total',
        'corporate_ids',
        'itens',
        'emitida_em',
        'enviada_em',
        'enviada_por',
    ];

    protected function casts(): array
    {
        return [
            'corporate_ids' => 'array',
            'itens' => 'array',
            'total' => 'decimal:2',
            'emitida_em' => 'datetime',
            'enviada_em' => 'datetime',
        ];
    }
}
