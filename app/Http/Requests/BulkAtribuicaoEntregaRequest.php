<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkAtribuicaoEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'corporate_ids' => ['nullable', 'array'],
            'corporate_ids.*' => ['exists:corporates,id'],
            'woo_order_ids' => ['nullable', 'array'],
            'woo_order_ids.*' => ['exists:woo_orders,id'],
            'user_id' => ['required', 'exists:users,id'],
            'dia_semana' => ['required', 'in:Segunda,Terca,Quarta,Quinta,Sexta,Sabado'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (empty($this->input('corporate_ids', [])) && empty($this->input('woo_order_ids', []))) {
                $validator->errors()->add('corporate_ids', 'Selecione pelo menos uma entrega.');
            }
        });
    }
}
