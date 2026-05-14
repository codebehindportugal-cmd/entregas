<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAtribuicaoEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:corporate,b2c'],
            'corporate_id' => ['required_if:tipo,corporate', 'nullable', 'exists:corporates,id'],
            'woo_order_id' => ['required_if:tipo,b2c', 'nullable', 'exists:woo_orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'dia_semana' => ['required', 'in:Segunda,Terca,Quarta,Quinta,Sexta,Sabado'],
        ];
    }
}
