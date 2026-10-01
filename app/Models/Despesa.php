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
        'viatura_id',
        'ficheiro_path',
        'notas',
        'origem',
        'origem_ref',
    ];

    /**
     * As categorias de despesa (01/10/2026). As do topo sao as que se usam;
     * as agricolas ficam no fim so para as despesas antigas que as tem.
     * As que chegam da gestao.ateneya.com usam estas mesmas chaves.
     */
    public const CATEGORIAS = [
        'entrada_produtos'  => 'Entrada de produtos',
        'compras'           => 'Compras a fornecedores',
        'combustivel'       => 'Combustível',
        'portagens'         => 'Portagens e estacionamento',
        'viaturas'          => 'Viaturas (reparações, seguros, inspeções)',
        'ordenados'         => 'Ordenados',
        'servicos'          => 'Serviços',
        'equipamento'       => 'Equipamento',
        'outro'             => 'Outro',
        'sementes'          => 'Sementes',
        'fertilizantes'     => 'Fertilizantes',
        'fitofarmaceuticos' => 'Fitofarmacêuticos',
        'mao_obra'          => 'Mão de obra',
    ];

    /** As que fazem sentido escolher para um carro. */
    public const CATEGORIAS_VIATURA = ['combustivel', 'portagens', 'viaturas'];

    public function categoriaLabel(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? ucfirst(str_replace('_', ' ', (string) $this->categoria));
    }

    public function viatura(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Viatura::class);
    }

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

    public function aiJobs(): HasMany
    {
        return $this->hasMany(AiJob::class);
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
