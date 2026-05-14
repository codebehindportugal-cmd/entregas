<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRegistoEntregaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pendente,entregue,falhou'],
            'nota' => ['nullable', 'string'],
            'fotos' => ['nullable', 'array', 'max:6'],
            'fotos.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,application/octet-stream', 'max:6144'],
        ];
    }

    public function messages(): array
    {
        return [
            'fotos.max' => 'Pode enviar no maximo 6 fotos de cada vez.',
            'fotos.*.mimetypes' => 'As fotos devem estar em JPG, PNG, WEBP, HEIC ou HEIF.',
            'fotos.*.max' => 'Cada foto pode ter no maximo 6 MB.',
        ];
    }
}
