<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public function fotos(): HasMany
    {
        return $this->hasMany(DespesaFoto::class)->orderBy('ordem');
    }

    public function capa(): HasOne
    {
        return $this->hasOne(DespesaFoto::class)->orderBy('ordem');
    }

    public function aiJobs(): HasMany
    {
        return $this->hasMany(AiJob::class);
    }

    public function getCapaUrlAttribute(): ?string
    {
        if ($this->relationLoaded('capa') && $this->capa) {
            return $this->capa->url;
        }
        if ($this->relationLoaded('fotos') && $this->fotos->isNotEmpty()) {
            return $this->fotos->first()->url;
        }
        if ($this->ficheiro_path) {
            return route('public-files.show', ['path' => $this->ficheiro_path]);
        }
        return null;
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
