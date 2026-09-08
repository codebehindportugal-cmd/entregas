<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NifPortugues implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nif = (string) $value;

        if (! preg_match('/^\d{9}$/', $nif)) {
            $fail(__('validation.nif'));

            return;
        }

        $soma = 0;

        for ($indice = 0; $indice < 8; $indice++) {
            $soma += ((int) $nif[$indice]) * (9 - $indice);
        }

        $digito = 11 - ($soma % 11);
        $digito = $digito >= 10 ? 0 : $digito;

        if ($digito !== (int) $nif[8]) {
            $fail(__('validation.nif'));
        }
    }
}
