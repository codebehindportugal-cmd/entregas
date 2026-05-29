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
            'texto' => ['required', 'string', 'max:5000'],
        ];
    }
}
