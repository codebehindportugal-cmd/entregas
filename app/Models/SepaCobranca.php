<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SepaCobranca extends Model
{
    protected $table = 'sepa_cobrancas';

    protected $fillable = [
        'sepa_mandato_id',
        'msg_id',
        'end_to_end_id',
        'data_cobranca',
        'mes_cobranca',
        'mes_inicio',
        'mes_fim',
        'n_meses',
        'valor',
        'descricao',
        'ultimo_mes_anterior',
        'xml',
        'gerado_por',
    ];

    protected function casts(): array
    {
        return [
            'data_cobranca' => 'date',
            'valor' => 'decimal:2',
            'n_meses' => 'integer',
        ];
    }

    public function mandato(): BelongsTo
    {
        return $this->belongsTo(SepaMandato::class, 'sepa_mandato_id');
    }

    public function nomeFicheiro(): string
    {
        $nome = \Illuminate\Support\Str::slug($this->mandato?->nome_devedor ?? 'cliente');

        return 'sepa_'.$this->data_cobranca->format('Y-m-d').'_'.$nome.'.xml';
    }
}
