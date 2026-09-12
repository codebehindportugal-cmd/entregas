<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Envelope de um lote de faturas.
 *
 * So valida o envelope: cada fatura e validada dentro do controlador, com as
 * regras de StoreFaturaApiRequest, para que uma fatura mal lida devolva o erro
 * dela e as outras entrem.
 */
class StoreFaturasLoteApiRequest extends FormRequest
{
    public const MAXIMO = 25;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'faturas' => ['required', 'array', 'min:1', 'max:'.self::MAXIMO],
            'faturas.*' => ['required', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'faturas.max' => 'No maximo '.self::MAXIMO.' faturas por pedido; divida o lote.',
            'faturas.*.array' => 'Cada elemento de "faturas" e o corpo de uma fatura (objecto), como no POST /faturas.',
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
