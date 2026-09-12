<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Uma encomenda lida de uma mensagem de WhatsApp, ainda por validar.
 *
 * A `unidade` de cada linha e de propósito `nullable`: se o cliente nao disse se
 * eram quilos ou unidades, o chat manda null e e o validador que decide se da
 * para assumir ou se ha mesmo de perguntar. Mandar um palpite aqui era esconder
 * a duvida no sitio onde ela ainda da para resolver.
 */
class ValidarEncomendaApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cliente' => ['nullable', 'array'],
            'cliente.nome' => ['nullable', 'string', 'max:255'],
            'cliente.telefone' => ['nullable', 'string', 'max:50'],
            'cliente.email' => ['nullable', 'email', 'max:255'],
            'cliente.morada' => ['nullable', 'string', 'max:255'],
            'cliente.codigo_postal' => ['nullable', 'string', 'max:20'],
            'cliente.cidade' => ['nullable', 'string', 'max:100'],
            'cliente.idioma' => ['nullable', 'in:pt,en'],

            // Encomenda antiga do mesmo cliente, para nao ter de repetir a morada.
            'perfil_woo_order_id' => ['nullable', 'integer', 'exists:woo_orders,id'],

            'dia_entrega' => ['nullable', 'in:segunda,quarta,sabado'],
            'data_entrega' => ['nullable', 'date'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'cupoes' => ['nullable', 'array'],
            'cupoes.*' => ['string', 'max:255'],

            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.texto' => ['required_without:linhas.*.woo_product_id', 'nullable', 'string', 'max:255'],
            'linhas.*.woo_product_id' => ['nullable', 'integer'],
            'linhas.*.quantidade' => ['required', 'numeric', 'gt:0'],
            'linhas.*.unidade' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'linhas.required' => 'A encomenda tem de trazer pelo menos uma linha.',
            'linhas.*.quantidade.required' => 'Cada linha precisa de uma quantidade.',
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
