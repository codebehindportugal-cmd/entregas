<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Localizacao extends Model
{
    protected $table = 'localizacoes';

    protected $fillable = ['chave', 'morada', 'cp', 'lat', 'lng', 'fonte'];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }
}
