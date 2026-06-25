<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Despesa extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'titulo',
        'numero_fatura',
        'fornecedor',
        'valor',
        'data',
        'categoria',
        'marca',
        'ficheiro_path',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'date',
            'valor' => 'decimal:2',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(FaturaItem::class);
    }

    public function getSubtotalCalculadoAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return (float) $this->items->sum('total_sem_iva');
        }

        return (float) $this->valor;
    }

    public function getIvaCalculadoAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return (float) $this->items->sum('total_iva_valor');
        }

        return 0.0;
    }

    public function getTotalFaturaAttribute(): float
    {
        if ($this->relationLoaded('items') && $this->items->isNotEmpty()) {
            return (float) $this->items->sum('total_com_iva');
        }

        return (float) $this->valor;
    }
}
