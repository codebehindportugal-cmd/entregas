<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorporateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'empresa' => ['required', 'string', 'max:255'],
            'sucursal' => ['nullable', 'string', 'max:255'],
            'morada_entrega' => ['nullable', 'string', 'max:500'],
            'dias_entrega' => ['required', 'array', 'min:1'],
            'dias_entrega.*' => ['in:Segunda,Terca,Quarta,Quinta,Sexta'],
            'periodicidade_entrega' => ['required', 'in:semanal,quinzenal'],
            'quinzenal_referencia' => ['nullable', 'date', 'required_if:periodicidade_entrega,quinzenal'],
            'horario_entrega' => ['nullable', 'string', 'max:255'],
            'responsavel_nome' => ['nullable', 'string', 'max:255'],
            'responsavel_telefone' => ['nullable', 'string', 'max:50'],
            'fatura_nome' => ['nullable', 'string', 'max:255'],
            'fatura_nif' => ['nullable', 'string', 'max:50'],
            'fatura_email' => ['nullable', 'email', 'max:255'],
            'fatura_morada' => ['nullable', 'string', 'max:500'],
            'numero_caixas' => ['required', 'integer', 'min:0'],
            'cabaz_tipo' => ['nullable', 'in:pequeno,medio,grande'],
            'cabaz_quantidade' => ['nullable', 'integer', 'min:1'],
            'peso_total' => ['nullable', 'numeric', 'min:0'],
            'frutas' => ['nullable', 'array'],
            'frutas.*' => ['nullable', 'numeric', 'min:0'],
            'frutas_por_dia' => ['nullable', 'array'],
            'frutas_por_dia.*' => ['nullable', 'array'],
            'frutas_por_dia.*.*' => ['nullable', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string'],
            'ativo' => ['nullable', 'boolean'],
        ];
    }
}
