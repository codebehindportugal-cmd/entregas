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
            'corporate_id' => ['required', 'exists:corporates,id'],
            'user_id' => ['required', 'exists:users,id'],
            'dia_semana' => ['required', 'in:Segunda,Terca,Quarta,Quinta,Sexta'],
        ];
    }
}
