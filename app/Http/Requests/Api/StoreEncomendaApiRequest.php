<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * A confirmacao de uma encomenda ja validada.
 *
 * So aceita um token vindo do /validar — nao ha caminho para criar uma encomenda
 * sem alguem ter visto as quantidades convertidas. A `referencia_externa` e o
 * que impede a mesma encomenda de ser criada duas vezes se a rede falhar a meio.
 */
class StoreEncomendaApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token_confirmacao' => ['required', 'string', 'size:40'],
            'referencia_externa' => ['required', 'string', 'max:100'],
            // Confirmado com o cliente. Explicito para nao haver criacao por engano.
            'confirmado' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmado.accepted' => 'So se cria a encomenda depois de confirmares as quantidades com o cliente (confirmado: true).',
            'referencia_externa.required' => 'Manda uma referencia_externa (ex.: wa-2026-09-12-joana-01) para o pedido poder ser repetido sem duplicar a encomenda.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'sucesso' => false,
            'dados' => null,
            'avisos' => [],
            'erros' => $validator->errors()->toArray(),
        ], 422));
    }
}
