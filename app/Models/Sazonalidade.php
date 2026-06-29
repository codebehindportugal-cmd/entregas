<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sazonalidade extends Model
{
    protected $table = 'sazonalidade';

    protected $fillable = [
        'produto',
        'categoria',
        'meses',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'meses' => 'array',
        ];
    }

    public function disponivelNoMes(int $mes): bool
    {
        return in_array($mes, $this->meses ?? [], true);
    }
}
