<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SepaMandato extends Model
{
    protected $table = 'sepa_mandatos';

    protected $fillable = [
        'nome_devedor',
        'nif',
        'iban',
        'bic',
        'mandato_ref',
        'codigo_pedido',
        'data_assinatura',
        'valor_mensal',
        'ultimo_mes_cobrado',
        'max_meses_por_cobranca',
        'dia_cobranca',
        'ativo',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'data_assinatura' => 'date',
            'valor_mensal' => 'decimal:2',
            'ativo' => 'boolean',
            'max_meses_por_cobranca' => 'integer',
            'dia_cobranca' => 'integer',
        ];
    }

    public function cobrancas(): HasMany
    {
        return $this->hasMany(SepaCobranca::class)->orderByDesc('data_cobranca');
    }

    /** IBAN com os ultimos 4 digitos visiveis, para listagens. */
    public function ibanMascarado(): string
    {
        $iban = (string) $this->iban;

        return substr($iban, 0, 4).' •••• '.substr($iban, -4);
    }
}
