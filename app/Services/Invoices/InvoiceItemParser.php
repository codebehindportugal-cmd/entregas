<?php

namespace App\Services\Invoices;

class InvoiceItemParser
{
    public function __construct(private readonly InvoiceDataNormalizer $normalizer) {}

    public function parse(string $text): array
    {
        $pipeItems = $this->parsePipeDelimited($text);

        if (!empty($pipeItems)) {
            return $pipeItems;
        }

        // Fallback: space-delimited "Description  qty  price  total"
        $items = [];
        $order = 0;

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if (strlen($line) < 5) {
                continue;
            }

            if (preg_match(
                '/^(.+?)\s{2,}(\d+(?:[.,]\d+)?)\s{2,}([\d.,]+)\s{2,}([\d.,]+)\s*$/',
                $line,
                $m
            )) {
                $qty   = $this->normalizer->normalizeAmount($m[2]) ?? 1;
                $price = $this->normalizer->normalizeAmount($m[3]);
                $total = $this->normalizer->normalizeAmount($m[4]);

                $items[] = [
                    'description' => trim($m[1]),
                    'quantity'    => $qty,
                    'unit_price'  => $price ?? 0,
                    'tax_rate'    => 0,
                    'tax_amount'  => 0,
                    'total'       => $total ?? 0,
                    'line_order'  => $order++,
                ];
            }
        }

        if (empty($items)) {
            $items[] = $this->placeholderRow();
        }

        return $items;
    }

    // ─────────────────── Pipe-delimited format ───────────────────

    /**
     * Parse lines from PT produce distributors:
     *   DATE|CODE|DESCRIPTION|[UNIT]|QTD_BRUTA|[devolução fields…]|QTD_LIQ|PRICE|IVA%|TOTAL
     *
     * Invariants (regardless of how many middle fields there are):
     *   fields[-1] = TOTAL
     *   fields[-2] = IVA%   (e.g. "06%", "23%")
     *   fields[-3] = PRICE
     *   fields[-4] = QTD_LIQ (net quantity, after returns)
     *   fields[2]  = DESCRIPTION
     */
    private function parsePipeDelimited(string $text): array
    {
        $items   = [];
        $order   = 0;

        // Pattern validates a line and captures: description, price, IVA numeric, total.
        // The unit code (KG/UN/UNI/CX/…) is optional — it may be absent in some lines.
        $pattern =
            '/^\d{8}\|[\w\/]+\|'           // DATE|CODE|
            . '(.+?)'                        // (1) description (lazy)
            . '(?:\|(?:KG|UN|UNI|CX|LT|SC|G|MT))?'  // optional |UNIT
            . '\|[\d.,]+'                    // first numeric (gross qty or lone qty)
            . '(?:\|[\d.,]+)*'               // any intermediate numeric fields
            . '\|([\d.,]+)'                  // (2) price
            . '\|(\d{1,2})\s*%'              // (3) IVA rate (6, 23, …)
            . '\|([\d.,]+)/i';               // (4) total

        foreach (explode("\n", $text) as $raw) {
            $line = $this->normalizePipeSeparators(trim($raw));

            if (preg_match($pattern, $line, $m)) {
                $description = trim($m[1]);
                $price       = $this->normalizer->normalizeAmount($m[2]);
                $taxRate     = (float) $m[3];
                $total       = $this->normalizer->normalizeAmount($m[4]);

                if ($price === null || $total === null || $total <= 0) {
                    continue;
                }

                // Net quantity is always at fields[-4] — before price|iva|total
                $fields = explode('|', $line);
                $count  = count($fields);
                $netQty = $this->normalizer->normalizeAmount($fields[$count - 4] ?? '') ?? 1;
                if ($netQty <= 0) {
                    $netQty = 1;
                }

                $items[] = [
                    'description' => $description,
                    'quantity'    => $netQty,
                    'unit_price'  => $price,
                    'tax_rate'    => $taxRate,
                    'tax_amount'  => round($total * ($taxRate / 100), 2),
                    'total'       => $total,
                    'line_order'  => $order++,
                ];
                continue;
            }

            // Fuzzy fallback: for date-prefixed lines where intermediate fields are garbled by
            // multi-column OCR bleed, work backwards from the last 3 fields (total|iva%|price).
            if (!preg_match('/^\d{8}\|/', $line)) {
                continue;
            }
            $fields = explode('|', $line);
            $count  = count($fields);
            if ($count < 5) {
                continue;
            }
            // OCR sometimes merges IVA% and total into one field: "06% 15,30 Contr"
            // Detect by checking if the last field starts with a percentage pattern.
            $lastField = $fields[$count - 1];
            if (preg_match('/^(\d{1,2})\s*%\s+(.+)/s', $lastField, $merged)) {
                // Layout: [date][code][desc/qty][price][iva%+total]
                // IVA + total merged in last field; price is at fields[-2]
                $taxRate      = (float) $merged[1];
                $total        = $this->extractFirstAmount($merged[2]);
                $price        = $this->extractFirstAmount($fields[$count - 2]);
                $netQtyField  = $fields[$count - 3] ?? ''; // may be desc with embedded qty
            } else {
                // Standard tail: [... qty][price][iva%][total]
                $total        = $this->extractFirstAmount($lastField);
                $taxRate      = null;
                if (preg_match('/(\d{1,2})\s*%/', $fields[$count - 2], $tm)) {
                    $taxRate = (float) $tm[1];
                }
                $price       = $this->extractFirstAmount($fields[$count - 3]);
                $netQtyField = $fields[$count - 4] ?? '';
            }

            if ($price === null || $total === null || $total <= 0 || $taxRate === null) {
                continue;
            }

            // Description is always field[2]; skip field[0]=date, field[1]=code
            $description = trim($fields[2] ?? '');
            if ($description === '') {
                continue;
            }

            $netQty     = $this->extractFirstAmount($netQtyField) ?? 0;
            $computedQty = $price > 0 ? round($total / $price, 2) : 1;
            // If the extracted qty is absent or differs >50% from total/price, use computed value
            if ($netQty <= 0 || $netQty > 10000
                || abs($netQty - $computedQty) / max($computedQty, 0.01) > 0.5
            ) {
                $netQty = $computedQty;
            }

            $items[] = [
                'description' => $description,
                'quantity'    => $netQty,
                'unit_price'  => $price,
                'tax_rate'    => $taxRate,
                'tax_amount'  => round($total * ($taxRate / 100), 2),
                'total'       => $total,
                'line_order'  => $order++,
            ];
        }

        return $items;
    }

    // ─────────────────── Helpers ───────────────────

    /**
     * Extract the first well-formed amount string from a field that may have trailing OCR garbage.
     * Falls back to normalizeAmount on the raw value if no structured amount is found.
     */
    private function extractFirstAmount(string $field): ?float
    {
        $re = '/([\d]{1,3}(?:[ .,]\d{3})*[.,]\d{2}|[\d]{4,}[.,]\d{2})/';
        if (preg_match($re, $field, $m)) {
            return $this->normalizer->normalizeAmount($m[1]);
        }
        return $this->normalizer->normalizeAmount($field);
    }

    /**
     * Normalise OCR-corrupted pipe separators on date-prefixed item lines.
     * Tesseract commonly reads | as ] ! } ¦ ¡ in structured table cells.
     */
    private function normalizePipeSeparators(string $line): string
    {
        if (!preg_match('/^\d{8}/', $line)) {
            return $line;
        }
        // Common single-char OCR misreadings of |
        $line = str_replace(['!', ']', '}', '¦', '¡'], '|', $line);
        // ) immediately after a digit and before whitespace+digit is also a misread |
        $line = preg_replace('/(\d)\)(?=\s+\d)/', '$1|', $line);
        // Strip spaces around pipe separators so the regex finds clean field boundaries
        $line = preg_replace('/\s*\|\s*/', '|', $line);
        return $line;
    }

    private function placeholderRow(): array
    {
        return [
            'description' => '',
            'quantity'    => 1,
            'unit_price'  => 0,
            'tax_rate'    => 0,
            'tax_amount'  => 0,
            'total'       => 0,
            'line_order'  => 0,
        ];
    }
}
