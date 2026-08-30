<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListaCabazItem extends Model
{
    protected $table = 'lista_cabaz_itens';

    protected $fillable = [
        'lista_cabaz_id',
        'cabaz_tipo',
        'produto',
        'categoria',
        'quantidade',
        'unidade',
        'peso_unitario_kg',
        'tabela_preco_item_id',
        'preco_unitario',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'quantidade' => 'decimal:3',
            'peso_unitario_kg' => 'decimal:4',
            'preco_unitario' => 'decimal:4',
        ];
    }

    public function listaCabaz(): BelongsTo
    {
        return $this->belongsTo(ListaCabaz::class);
    }

    public function tabelaPrecoItem(): BelongsTo
    {
        return $this->belongsTo(TabelaPrecoItem::class);
    }

    public function precoEfetivo(): ?float
    {
        if ($this->tabelaPrecoItem) {
            return (float) $this->tabelaPrecoItem->preco_kg;
        }

        return $this->preco_unitario ? (float) $this->preco_unitario : null;
    }

    public function custoUnitario(): ?float
    {
        $preco = $this->precoEfetivo();

        if ($preco === null) {
            return null;
        }

        $quantidadeKg = $this->quantidadeParaCustoKg();

        if ($quantidadeKg !== null) {
            return round($quantidadeKg * $preco, 4);
        }

        // Produtos vendidos a unidade e sem peso indicado (courgette, salsa,
        // coracao): o preco escrito e o de cada unidade, nao o do quilo.
        // So vale para precos escritos a mao — os da tabela de precos sao
        // sempre por quilo.
        if ($this->tabelaPrecoItem === null && $this->ehAUnidade()) {
            return round((float) $this->quantidade * $preco, 4);
        }

        return null;
    }

    /** A linha e contada a unidade (nao tem peso nem unidade de peso). */
    public function ehAUnidade(): bool
    {
        $unidade = mb_strtolower(trim((string) $this->unidade));

        return $this->peso_unitario_kg === null
            && in_array($unidade, ['un', 'uni', 'unid', 'unidade', 'unidades', ''], true);
    }

    public function quantidadeParaCustoKg(): ?float
    {
        $unidade = mb_strtolower(trim((string) $this->unidade));
        $quantidade = (float) $this->quantidade;

        if (in_array($unidade, ['kg', 'quilo', 'quilos'], true)) {
            return $quantidade;
        }

        if (in_array($unidade, ['g', 'gr', 'grama', 'gramas'], true)) {
            return round($quantidade / 1000, 4);
        }

        if ($this->peso_unitario_kg !== null) {
            return round($quantidade * (float) $this->peso_unitario_kg, 4);
        }

        return null;
    }
}
