<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'woo_id',
        'name',
        'slug',
        'sku',
        'type',
        'status',
        'permalink',
        'image_url',
        'price',
        'regular_price',
        'sale_price',
        'stock_status',
        'purchasable',
        'em_epoca',
        'disponivel_compra',
        'epoca',
        'tabela_preco_item_id',
        'custo_quantidade',
        'custo_unidade',
        'unidade_venda',
        'formato_qtd',
        'formato_unidade',
        'peso_medio_kg',
        'qtd_min',
        'qtd_max',
        'aliases',
        'unidades_confirmadas',
        'categories',
        'raw_payload',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'regular_price' => 'decimal:4',
            'sale_price' => 'decimal:4',
            'purchasable' => 'boolean',
            'em_epoca' => 'boolean',
            'disponivel_compra' => 'boolean',
            'custo_quantidade' => 'decimal:4',
            'formato_qtd' => 'decimal:3',
            'peso_medio_kg' => 'decimal:3',
            'qtd_min' => 'integer',
            'qtd_max' => 'integer',
            'aliases' => 'array',
            'unidades_confirmadas' => 'boolean',
            'categories' => 'array',
            'raw_payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function tabelaPrecoItem(): BelongsTo
    {
        return $this->belongsTo(TabelaPrecoItem::class);
    }

    public function precoVenda(): ?float
    {
        if ($this->sale_price !== null && (float) $this->sale_price > 0) {
            return (float) $this->sale_price;
        }

        if ($this->regular_price !== null && (float) $this->regular_price > 0) {
            return (float) $this->regular_price;
        }

        return $this->price !== null ? (float) $this->price : null;
    }

    public function vendidoAoPeso(): bool
    {
        return $this->unidade_venda === 'peso';
    }

    /**
     * Quantos kg leva uma linha da encomenda.
     *
     * Nos produtos ao peso e o tamanho da embalagem; nos produtos a unidade e o
     * peso medio, que muitas vezes nao esta preenchido — e nesse caso nao se
     * converte nada, pergunta-se ao cliente.
     */
    public function conteudoEmKg(): ?float
    {
        if ($this->vendidoAoPeso() && $this->formato_unidade === 'kg') {
            return (float) $this->formato_qtd;
        }

        return $this->peso_medio_kg !== null ? (float) $this->peso_medio_kg : null;
    }

    public function descricaoFormato(): string
    {
        if ($this->vendidoAoPeso()) {
            return 'embalagem de '.$this->formataKg((float) $this->formato_qtd);
        }

        $peso = $this->peso_medio_kg !== null ? (float) $this->peso_medio_kg : null;

        return $peso !== null
            ? 'a unidade (~'.$this->formataKg($peso).')'
            : 'a unidade';
    }

    /** Os nomes por que este produto pode ser tratado numa mensagem. */
    public function nomesConhecidos(): array
    {
        return collect([$this->name, $this->slug, $this->sku])
            ->merge(is_array($this->aliases) ? $this->aliases : [])
            ->filter(fn (mixed $nome): bool => filled($nome))
            ->map(fn (mixed $nome): string => (string) $nome)
            ->unique()
            ->values()
            ->all();
    }

    private function formataKg(float $kg): string
    {
        return $kg < 1
            ? rtrim(rtrim(number_format($kg * 1000, 0, ',', ''), '0'), ',').' g'
            : rtrim(rtrim(number_format($kg, 3, ',', ''), '0'), ',').' kg';
    }

    public function compraAtiva(): bool
    {
        return $this->status === 'publish'
            && $this->stock_status === 'instock'
            && $this->purchasable
            && $this->em_epoca
            && $this->disponivel_compra;
    }

    public function custoCompra(): ?float
    {
        if ($this->tabelaPrecoItem === null || $this->tabelaPrecoItem->preco_kg === null) {
            return null;
        }

        return round((float) $this->custo_quantidade * (float) $this->tabelaPrecoItem->preco_kg, 4);
    }

    public function margem(): ?float
    {
        $venda = $this->precoVenda();
        $custo = $this->custoCompra();

        return $venda !== null && $custo !== null ? round($venda - $custo, 4) : null;
    }

    public function margemPercentagem(): ?float
    {
        $venda = $this->precoVenda();
        $margem = $this->margem();

        return $venda !== null && $venda > 0 && $margem !== null ? round(($margem / $venda) * 100, 1) : null;
    }
}
