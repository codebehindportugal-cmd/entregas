<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Uma fatura de compra (entrada de produtos) a entrar pela API.
 *
 * As chaves sao as do agro.codebehind.pt, para a mesma leitura de uma foto
 * servir os dois sitios. Onde este projecto tem nome proprio para a mesma coisa
 * — `unidades_por_quantidade` e o tamanho da embalagem, `unidade_compra` e a
 * unidade — aceitam-se os dois nomes e o daqui ganha.
 */
class StoreFaturaApiRequest extends FormRequest
{
    public const TAXAS_IVA = [0, 6, 13, 23];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::regrasFatura();
    }

    /**
     * As regras de uma fatura, sem prefixo.
     *
     * Publicas e static porque o lote valida cada fatura por si: uma mal lida
     * nao pode levar atras as outras do mesmo envio.
     *
     * @return array<string, mixed>
     */
    public static function regrasFatura(): array
    {
        return [
            'titulo' => ['nullable', 'string', 'max:255'],
            'numero_fatura' => ['nullable', 'string', 'max:100'],
            'fornecedor' => ['nullable', 'string', 'max:255'],
            'data' => ['required', 'date'],
            // Total a pagar, com IVA. Sem ele soma-se pelas linhas.
            'valor' => ['nullable', 'numeric', 'min:0'],
            'categoria' => ['nullable', 'string', 'max:50'],
            'notas' => ['nullable', 'string'],

            'linhas' => ['required', 'array', 'min:1'],
            'linhas.*.descricao' => ['required', 'string', 'max:255'],
            'linhas.*.quantidade' => ['required', 'numeric', 'gt:0'],
            'linhas.*.preco_unitario' => ['required', 'numeric', 'min:0'],
            'linhas.*.iva_percentagem' => ['required', 'numeric', Rule::in(self::TAXAS_IVA)],
            // Tamanho da embalagem: o nome daqui e o do agro, os dois aceites.
            'linhas.*.unidade_compra' => ['nullable', 'string', 'max:20'],
            'linhas.*.unidades_por_quantidade' => ['nullable', 'numeric', 'gt:0'],
            'linhas.*.quantidade_unidades' => ['nullable', 'numeric', 'min:0'],
            'linhas.*.unidade_embalagem' => ['nullable', 'string', 'max:20'],
            'linhas.*.conteudo_embalagem' => ['nullable', 'numeric', 'gt:0'],
            // Este projecto nao tem coluna de desconto: o desconto e aplicado ao
            // preco unitario e fica dito nas notas da linha.
            'linhas.*.desconto_percentagem' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'linhas.*.notas' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return self::mensagensFatura();
    }

    /** @return array<string, string> */
    public static function mensagensFatura(): array
    {
        $taxas = implode(', ', self::TAXAS_IVA);

        return [
            'linhas.*.iva_percentagem.in' => "Taxa de IVA invalida. Valores aceites: {$taxas}.",
            'linhas.required' => 'Uma entrada de produtos tem de trazer pelo menos uma linha.',
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
