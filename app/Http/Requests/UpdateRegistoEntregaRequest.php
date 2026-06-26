<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

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
            'fotos.*' => ['nullable', 'file', 'max:10240', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value === null) {
                    return;
                }
                if (! $value instanceof UploadedFile || ! $value->isValid() || ! $this->validDeliveryPhoto($value)) {
                    $fail('As fotos devem ser imagens validas em JPG, PNG, WEBP, HEIC ou HEIF.');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'fotos.max' => 'Pode enviar no maximo 6 fotos de cada vez.',
            'fotos.*.uploaded' => 'A foto nao conseguiu chegar ao servidor. Tente uma foto mais leve ou confirme no servidor upload_max_filesize, post_max_size e permissoes do diretorio temporario do PHP.',
            'fotos.*.max' => 'Cada foto pode ter no maximo 10 MB.',
        ];
    }

    private function validDeliveryPhoto(UploadedFile $file): bool
    {
        $mime = (string) $file->getMimeType();

        if (in_array($mime, ['image/heic', 'image/heif'], true)) {
            return $this->isHeicOrHeif($file);
        }

        if (in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/webp'], true)) {
            return @getimagesize($file->getRealPath()) !== false;
        }

        // MIME type desconhecido ou inesperado (acontece em alguns servidores onde o finfo
        // reporta application/octet-stream para fotos tiradas em Android/iOS).
        // Tenta primeiro a assinatura HEIC/HEIF; se não corresponder, usa getimagesize
        // como fallback para aceitar qualquer formato de imagem válido.
        if ($this->isHeicOrHeif($file)) {
            return true;
        }

        return @getimagesize($file->getRealPath()) !== false;
    }

    private function isHeicOrHeif(UploadedFile $file): bool
    {
        $handle = @fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 64);
        fclose($handle);

        if ($header === false || substr($header, 4, 4) !== 'ftyp') {
            return false;
        }

        foreach (['heic', 'heix', 'hevc', 'hevx', 'heif', 'mif1', 'msf1'] as $brand) {
            if (str_contains($header, $brand)) {
                return true;
            }
        }

        return false;
    }
}
