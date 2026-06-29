<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabazTemplate extends Model
{
    protected $table = 'cabaz_templates';

    protected $fillable = [
        'cabaz_tipo',
        'categoria',
        'quantidade_itens',
        'quantidade_por_item',
        'unidade',
        'peso_unitario_kg',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'quantidade_itens' => 'integer',
            'quantidade_por_item' => 'decimal:3',
            'peso_unitario_kg' => 'decimal:3',
            'ordem' => 'integer',
        ];
    }
}
