<?php

namespace App\Services\Invoices;

class InvoiceDataNormalizer
{
    public function normalizeAmount(?string $value): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // Remove currency symbols, non-breaking spaces, and regular whitespace
        // This also handles "1 194,40" (space as thousands separator) → "1194,40"
        $value = preg_replace('/[€$£\s\xc2\xa0]/u', '', trim($value));

        if ($value === '' || $value === '-') {
            return null;
        }

        // European format with thousands dot and decimal comma: 1.234,56
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d+$/', $value)) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        // Plain comma as decimal separator: 1234,56
        elseif (preg_match('/^\d+,\d{1,2}$/', $value)) {
            $value = str_replace(',', '.', $value);
        }
        // European thousands without cents: 1.234
        elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        }
        // Otherwise assume standard period-decimal format

        return is_numeric($value) ? (float) $value : null;
    }

    public function normalizeDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        // yyyy-mm-dd (already ISO)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        // yyyy.mm.dd  (distributor format: "2026.06.19")
        if (preg_match('/^(\d{4})\.(\d{2})\.(\d{2})$/', $value, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        // yyyy/mm/dd
        if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $value, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        // dd/mm/yyyy or dd-mm-yyyy
        if (preg_match('/^(\d{2})[\/\-](\d{2})[\/\-](\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // dd.mm.yyyy  (German/PT alternative)
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return null;
    }
}
