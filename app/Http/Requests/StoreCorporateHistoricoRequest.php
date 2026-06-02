<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCorporateHistoricoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'data' => ['required', 'date'],
            'tipo' => ['nullable', 'in:nota,nao_entregamos,entrega_parcial,entrega_extra'],
            'pecas_entregues' => ['nullable', 'required_if:tipo,entrega_parcial,entrega_extra', 'integer', 'min:0'],
            'texto' => ['required', 'string', 'max:5000'],
        ];
    }
}
