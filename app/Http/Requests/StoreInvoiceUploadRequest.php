<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxKb = (int) config('invoices.max_upload_size', 10240);

        return [
            'invoice_files'   => ['required', 'array', 'min:1', 'max:20'],
            'invoice_files.*' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', "max:{$maxKb}"],
        ];
    }

    public function messages(): array
    {
        $maxMb = round(config('invoices.max_upload_size', 10240) / 1024);

        return [
            'invoice_files.required'   => 'Selecione pelo menos um ficheiro.',
            'invoice_files.min'        => 'Selecione pelo menos um ficheiro.',
            'invoice_files.max'        => 'Máximo 20 ficheiros por fatura.',
            'invoice_files.*.required' => 'Ficheiro inválido.',
            'invoice_files.*.mimes'    => 'Apenas PDF, JPG ou PNG são aceites.',
            'invoice_files.*.max'      => "Cada ficheiro não pode exceder {$maxMb} MB.",
        ];
    }

    /**
     * Extra validation: if any file is a PDF, only one file is allowed.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $files = $this->file('invoice_files') ?? [];
            if (count($files) <= 1) {
                return;
            }
            foreach ($files as $file) {
                if ($file && in_array($file->getMimeType(), ['application/pdf'], true)) {
                    $v->errors()->add('invoice_files', 'Para um PDF, selecione apenas um ficheiro. Para várias páginas, use imagens JPG/PNG.');
                    return;
                }
            }
        });
    }
}
