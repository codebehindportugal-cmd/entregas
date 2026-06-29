<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_name'       => ['nullable', 'string', 'max:255'],
            'supplier_tax_number' => ['nullable', 'string', 'max:20'],
            'invoice_number'      => ['nullable', 'string', 'max:100'],
            'invoice_date'        => ['nullable', 'date'],
            'due_date'            => ['nullable', 'date'],
            'subtotal'            => ['nullable', 'numeric', 'min:0'],
            'tax_total'           => ['nullable', 'numeric', 'min:0'],
            'total'               => ['nullable', 'numeric', 'min:0'],
            'currency'            => ['nullable', 'string', 'size:3'],
        ];
    }
}
