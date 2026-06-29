<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_name'       => ['required', 'string', 'max:255'],
            'supplier_tax_number' => ['nullable', 'string', 'max:20'],
            'invoice_number'      => ['required', 'string', 'max:100'],
            'invoice_date'        => ['required', 'date'],
            'due_date'            => ['nullable', 'date'],
            'subtotal'            => ['nullable', 'numeric', 'min:0'],
            'tax_total'           => ['nullable', 'numeric', 'min:0'],
            'total'               => ['required', 'numeric', 'min:0'],
            'currency'            => ['required', 'string', 'size:3'],
            'items'               => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string', 'max:1000'],
            'items.*.quantity'    => ['required_with:items', 'numeric', 'min:0'],
            'items.*.unit_price'  => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_amount'  => ['nullable', 'numeric', 'min:0'],
            'items.*.total'       => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_name.required'  => 'O nome do fornecedor é obrigatório.',
            'invoice_number.required' => 'O número de fatura é obrigatório.',
            'invoice_date.required'   => 'A data da fatura é obrigatória.',
            'total.required'          => 'O valor total é obrigatório.',
        ];
    }
}
