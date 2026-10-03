<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma encomenda que chegou por email ou WhatsApp e ainda nao foi confirmada.
 *
 * Estados:
 * - novo: chegou, ainda ninguem a interpretou (ex.: mensagem do webhook do WhatsApp)
 * - pronto: interpretada e validada sem erros, so falta carregar em Confirmar
 * - com_duvidas: interpretada, mas ha erros do validador ou perguntas a fazer
 * - falta_telefone: nao deu para identificar o cliente (sem telefone)
 * - criado: a encomenda ja esta no WooCommerce
 * - descartado: nao era encomenda, ou foi tratada por outro lado
 */
class PedidoRecebido extends Model
{
    protected $table = 'pedidos_recebidos';

    public const CANAIS = ['email', 'whatsapp', 'manual'];

    public const ESTADOS = [
        'novo' => 'Por interpretar',
        'pronto' => 'Pronto a confirmar',
        'com_duvidas' => 'Com dúvidas',
        'falta_telefone' => 'Falta telefone',
        'criado' => 'Encomenda criada',
        'descartado' => 'Descartado',
    ];

    /** Os estados que ainda pedem trabalho. */
    public const ABERTOS = ['novo', 'pronto', 'com_duvidas', 'falta_telefone'];

    protected $fillable = [
        'canal',
        'origem_id',
        'remetente',
        'assunto',
        'recebido_em',
        'texto_original',
        'pedido',
        'resumo',
        'avisos',
        'erros',
        'duvidas',
        'estado',
        'woo_order_id',
        'tratado_por',
        'tratado_em',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'recebido_em' => 'datetime',
            'tratado_em' => 'datetime',
            'pedido' => 'array',
            'resumo' => 'array',
            'avisos' => 'array',
            'erros' => 'array',
            'duvidas' => 'array',
        ];
    }

    public function wooOrder(): BelongsTo
    {
        return $this->belongsTo(WooOrder::class);
    }

    public function tratadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tratado_por');
    }

    public function aberto(): bool
    {
        return in_array($this->estado, self::ABERTOS, true);
    }

    public function etiquetaEstado(): string
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /** Referencia unica no WooOrder, para nunca criar duas encomendas do mesmo pedido. */
    public function referenciaExterna(): string
    {
        return 'pr-'.$this->id;
    }

    public function nomeCliente(): ?string
    {
        return $this->resumo['cliente']['nome'] ?? $this->pedido['cliente']['nome'] ?? null;
    }
}
