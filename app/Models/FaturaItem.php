<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaturaItem extends Model
{
    protected $fillable = [
        'despesa_id',
        'descricao',
        'quantidade',
        'unidade_compra',
        'unidades_por_quantidade',
        'quantidade_unidades',
        'preco_unitario',
        'iva_percentagem',
        'notas',
    ];

    protected $appends = ['total_sem_iva', 'total_iva_valor', 'total_com_iva', 'custo_unitario'];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'unidades_por_quantidade' => 'decimal:3',
            'quantidade_unidades' => 'decimal:3',
            'preco_unitario' => 'decimal:4',
            'iva_percentagem' => 'decimal:2',
        ];
    }

    public function despesa(): BelongsTo
    {
        return $this->belongsTo(Despesa::class);
    }

    public function getTotalSemIvaAttribute(): float
    {
        return round((float) $this->quantidade * (float) $this->preco_unitario, 4);
    }

    public function getTotalIvaValorAttribute(): float
    {
        return round($this->total_sem_iva * ((float) $this->iva_percentagem / 100), 4);
    }

    public function getTotalComIvaAttribute(): float
    {
        return round($this->total_sem_iva + $this->total_iva_valor, 4);
    }

    public function getCustoUnitarioAttribute(): ?float
    {
        $unidades = (float) $this->quantidade_unidades;

        if ($unidades <= 0) {
            return null;
        }

        return round($this->total_sem_iva / $unidades, 4);
    }
}
