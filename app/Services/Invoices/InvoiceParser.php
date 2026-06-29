<?php

namespace App\Services\Invoices;

class InvoiceParser
{
    // PT legal entity suffixes — a line containing these is likely the company name
    private const PT_ENTITY_SUFFIXES = '/\b(?:Lda\.?|S\.?A\.?|SGPS|Unipessoal|Unip\.?|E\.?I\.?R\.?L\.?|S\.?R\.?L\.?)\b/iu';

    // Amount with 2 decimal places.
    // Alt 1: standard European format with thousands separator (dot/space/comma): "1.194,40" / "1 275,07"
    // Alt 2: 4+ digit amounts written without thousands separator:              "1194,40" / "1275,07"
    private const AMOUNT_RE = '([\d]{1,3}(?:[ .,]\d{3})*[.,]\d{2}|[\d]{4,}[.,]\d{2})';

    // Keywords that signal a line is NOT the company name
    private const SKIP_LINE_RE = '/^\s*(?:---|\d|NIF|NIPC|Contribuinte|Fatur|Factur|Invoice|Recibo|Nota\s+de|Data|Tel[ef]|Fax|Email|www\.|Rua|Av[.\s]|Largo|Praça|Trav|Est[ra]|Bl\.|Ap[to]|C\.P\.|CP\s|\d{4}-\d{3}|http|Ref\.?|Nr\.?|N[º°]|IBAN|NIB|Swift|ATCUD|Hora\s+de|Natureza|Condi[çc]|P[áa]gina)/iu';

    public function __construct(private readonly InvoiceDataNormalizer $normalizer) {}

    public function parse(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $data = [
            'supplier_name'       => $this->extractSupplierName($text),
            'supplier_tax_number' => $this->extractTaxNumber($text),
            'invoice_number'      => $this->extractInvoiceNumber($text),
            'invoice_date'        => $this->extractInvoiceDate($text),
            'due_date'            => $this->extractDueDate($text),
            'subtotal'            => $this->extractSubtotal($text),
            'tax_total'           => $this->extractTaxTotal($text),
            'total'               => $this->extractTotal($text),
            'currency'            => $this->extractCurrency($text),
            'atcud'               => $this->extractAtcud($text),
        ];

        $data['confidence'] = $this->buildConfidence($data);

        return $data;
    }

    // ─────────────────── NIF / NIPC ───────────────────

    private function extractTaxNumber(string $text): ?string
    {
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);

            // Skip customer / buyer NIF lines
            if (preg_match('/cliente|adquirente|comprador|destinat[áa]rio|Vos[sS]o|V\/\s*Contribuinte|compra/iu', $line)) {
                continue;
            }

            // "Contribuinte Nº 508826110"
            if (preg_match(
                '/(?:N\.?\s*[Iiº°]\s*F\.?|N[º°\.]\s*[Cc]ontrib(?:uinte)?\.?|N[º°\.]\s*[Ff]iscal|Contribuinte)\s*[Nº°\.]*\s*[:\-]?\s*([1-9]\d{8})/ui',
                $line,
                $m
            )) {
                return $m[1];
            }

            // "NIF: PT508826110" or "NIF: 508826110"
            if (preg_match(
                '/\bNIF(?:C)?\s*[:\-]?\s*PT\s*([1-9]\d{8})/ui',
                $line,
                $m
            )) {
                return $m[1];
            }

            if (preg_match(
                '/\bNIPC?\s*[:\-]?\s*([1-9]\d{8})/ui',
                $line,
                $m
            )) {
                return $m[1];
            }
        }

        // Fallback: first labeled NIF anywhere
        if (preg_match(
            '/(?:NIF|NIPC|Contribuinte)\s*[:\-Nº°\.]*\s*(?:PT\s*)?([1-9]\d{8})/ui',
            $text,
            $m
        )) {
            return $m[1];
        }

        return null;
    }

    // ─────────────────── Invoice number ───────────────────

    private function extractInvoiceNumber(string $text): ?string
    {
        // FAC format used by PT fruit/veg distributors: "FAC 26A/6678"
        if (preg_match('/\b(FAC\s+[\dA-Z]+\/\d+)/u', $text, $m)) {
            return $this->cleanInvoiceNumber(trim($m[1]));
        }

        // "Fatura n.º FA 2024/001", "N.º Fatura: FA/2024/001"
        if (preg_match(
            '/(?:N[º°\.]\s*(?:de\s+)?(?:[Ff]atura|[Ff]actura)|(?:[Ff]atura|[Ff]actura|Invoice)\s+[Nn][º°\.]?\s*(?:de\s+)?)\s*[:\-]?\s*([A-Z0-9][A-Z0-9\/\-\._ ]{1,30})/u',
            $text,
            $m
        )) {
            return $this->cleanInvoiceNumber(trim($m[1]));
        }

        // "Fatura FA 2024/001"
        if (preg_match(
            '/(?:[Ff]atura|[Ff]actura|Invoice)\s+([A-Z]{1,3}[\s\/\-]?[\d]{4}[\/\-][\d]{1,6})/u',
            $text,
            $m
        )) {
            return $this->cleanInvoiceNumber(trim($m[1]));
        }

        // Standalone PT series codes at start of line: "FA/2024/001", "FR 2024/042"
        if (preg_match(
            '/^((?:FA|FR|FT|FS|FP|VD|NC|ND|OR)\s*[\/\-]?\s*\d{4}[\/\-]\d{1,6})/mu',
            $text,
            $m
        )) {
            return $this->cleanInvoiceNumber(trim($m[1]));
        }

        // Generic "N.º:" followed by alphanumeric series
        if (preg_match(
            '/N[º°\.][º°\.]?\s*[:\-]?\s*([A-Z]{1,3}[\s\/\-]\d{4}[\/\-]\d{1,6})/u',
            $text,
            $m
        )) {
            return $this->cleanInvoiceNumber(trim($m[1]));
        }

        return null;
    }

    private function cleanInvoiceNumber(string $raw): string
    {
        return rtrim(preg_replace('/\s{2,}.*/', '', $raw), " \t.");
    }

    // ─────────────────── Dates ───────────────────

    private function extractInvoiceDate(string $text): ?string
    {
        // Covers dd/mm/yyyy, dd-mm-yyyy, dd.mm.yyyy, yyyy-mm-dd, yyyy/mm/dd, yyyy.mm.dd
        $dateRe = '(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4}|\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2})';

        foreach ([
            "/(?:Data\s+(?:da\s+)?(?:[Ff]atura|[Ff]actura|[Ee]miss[aã]o|doc(?:umento)?)|[Ee]miss[aã]o)\s*[:\-]?\s*{$dateRe}/ui",
            "/(?:^|\n)\s*Data\s*[:\-]\s*{$dateRe}/ui",
        ] as $p) {
            if (preg_match($p, $text, $m)) {
                return $this->normalizer->normalizeDate(end($m));
            }
        }

        // Table header row: "Nº FAC 264/6678 ... 30 Dias 2026-06-19 2026-07-19"
        // First date in the FAC row is the emission date
        if (preg_match('/\bFAC\b[^\n]+?(\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2})/u', $text, $m)) {
            return $this->normalizer->normalizeDate($m[1]);
        }

        // Fallback: first ISO-style date (YYYY-MM-DD) in the first 3000 chars
        if (preg_match('/\b(\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2})\b/', substr($text, 0, 3000), $m)) {
            return $this->normalizer->normalizeDate($m[1]);
        }

        return null;
    }

    private function extractDueDate(string $text): ?string
    {
        $dateRe = '(\d{2}[\/\-\.]\d{2}[\/\-\.]\d{4}|\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2})';

        foreach ([
            "/(?:Data\s+de\s+)?(?:Vencimento|Prazo\s+(?:de\s+)?[Pp]agamento|Data\s+limite(?:\s+de\s+pagamento)?|Limite\s+de\s+pagamento)\s*[:\-]?\s*{$dateRe}/ui",
            "/Due\s+[Dd]ate\s*[:\-]?\s*{$dateRe}/ui",
        ] as $p) {
            if (preg_match($p, $text, $m)) {
                return $this->normalizer->normalizeDate(end($m));
            }
        }

        // Table header row: second date in the FAC row is the due date
        if (preg_match(
            '/\bFAC\b[^\n]+?\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2}[^\n]+?(\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2})/u',
            $text,
            $m
        )) {
            return $this->normalizer->normalizeDate($m[1]);
        }

        return null;
    }

    // ─────────────────── Monetary amounts ───────────────────

    private function extractSubtotal(string $text): ?float
    {
        foreach ([
            // "Total Liquido 1.129,21" — net after discounts, before VAT
            '/(?:Total\s+L[íi]quido|Liq\.?)[^\d\n]{0,20}' . self::AMOUNT_RE . '/ui',
            // Standard labels
            '/(?:Sub(?:\s*-?\s*)?[Tt]otal|Base\s+tribut[áa]vel|Base\s+impon[íi]vel|Valor\s+s\/\s*IVA|Total\s+s\/\s*IVA|Base\s+de\s+incid[êe]ncia|Incid[êe]ncia|Valor\s+tribut[áa]vel)[^\d\n]{0,20}' . self::AMOUNT_RE . '/ui',
            // "Total Mercadorias 887,64" — OCR may insert | or spaces between label and amount
            '/Total\s+Mercadorias[^\d\n]{0,20}' . self::AMOUNT_RE . '/ui',
        ] as $p) {
            if (preg_match($p, $text, $m)) {
                $v = $this->normalizer->normalizeAmount(end($m));
                if ($v !== null && $v > 0) {
                    return $v;
                }
            }
        }

        return null;
    }

    private function extractTaxTotal(string $text): ?float
    {
        // "Total de I.V.A." — OCR may drop the I or add/remove dots: ".V.A.", "LV.A.", "I V A"
        if (preg_match('/Total\s+de\s+\.?[Ii]?\.?\s*[Vv]\.?\s*[Aa]\.?\s*[^\d\n]{0,20}(.+)/ui', $text, $line)) {
            if (preg_match_all('/' . self::AMOUNT_RE . '/u', $line[1], $amounts)) {
                $sum = array_reduce($amounts[1], function ($carry, $raw) {
                    $v = $this->normalizer->normalizeAmount($raw);
                    return $carry + ($v ?? 0);
                }, 0.0);
                if ($sum > 0) {
                    return $sum;
                }
            }
        }

        foreach ([
            // "IVA (23%): 23,00" or "IVA 23%: 23,00"
            '/\bIVA\b\s*(?:\(?\d{1,2}[,.]?\d*%\)?)?\s*[:\-]?\s*' . self::AMOUNT_RE . '/ui',
            // "Total IVA: 23,00"
            '/(?:Total\s+(?:de\s+)?IVA|Valor\s+(?:do\s+)?IVA|IVA\s+[Tt]otal)\s*[:\-]?\s*' . self::AMOUNT_RE . '/ui',
            '/(?:VAT\s+[Tt]otal|Tax\s+[Tt]otal)\s*[:\-]?\s*' . self::AMOUNT_RE . '/ui',
        ] as $p) {
            if (preg_match($p, $text, $m)) {
                $v = $this->normalizer->normalizeAmount(end($m));
                if ($v !== null && $v > 0) {
                    return $v;
                }
            }
        }

        return null;
    }

    private function extractTotal(string $text): ?float
    {
        $patterns = [
            // "TOTAL A PAGAR: 1 194,40 €" — OCR may insert | or spaces between label and amount
            '/TOTAL\s+A\s+PAGAR[^\d\n]{0,20}' . self::AMOUNT_RE . '/ui',
            '/(?:Total\s+a\s+pagar|Valor\s+a\s+(?:pagar|liquidar)|Montante\s+(?:total|a\s+pagar))[^\d\n]{0,20}' . self::AMOUNT_RE . '/ui',
            '/(?:Valor\s+total|Total\s+geral|TOTAL\s+GERAL)[^\d\n]{0,20}' . self::AMOUNT_RE . '/ui',
            '/(?:Grand\s+[Tt]otal|Total\s+[Aa]mount)[^\d\n]{0,20}' . self::AMOUNT_RE . '/ui',
            '/^TOTAL\s+' . self::AMOUNT_RE . '/mu',
            '/^Total\s*[:\-]\s*' . self::AMOUNT_RE . '/mu',
        ];

        foreach ($patterns as $p) {
            if (preg_match($p, $text, $m)) {
                $v = $this->normalizer->normalizeAmount(end($m));
                if ($v !== null && $v > 0) {
                    return $v;
                }
            }
        }

        return $this->fallbackTotalFromLines($text);
    }

    private function fallbackTotalFromLines(string $text): ?float
    {
        $best = null;

        foreach (explode("\n", $text) as $line) {
            if (!preg_match('/\btotal\b/iu', $line)) {
                continue;
            }
            if (preg_match_all('/' . self::AMOUNT_RE . '/u', $line, $matches)) {
                foreach ($matches[1] as $raw) {
                    $v = $this->normalizer->normalizeAmount($raw);
                    if ($v !== null && $v > 5 && ($best === null || $v > $best)) {
                        $best = $v;
                    }
                }
            }
        }

        return $best;
    }

    // ─────────────────── ATCUD ───────────────────

    private function extractAtcud(string $text): ?string
    {
        // Minimum 4 chars before dash — tolerates OCR noise that may drop/add characters
        if (preg_match('/\bATCUD\s*[:\-]?\s*([A-Z0-9]{4,20}-\d{1,10})/ui', $text, $m)) {
            return strtoupper(trim($m[1]));
        }

        return null;
    }

    // ─────────────────── Currency ───────────────────

    private function extractCurrency(string $text): string
    {
        if (preg_match('/\b(USD|GBP|BRL|CHF)\b/', $text, $m)) {
            return $m[1];
        }

        return 'EUR';
    }

    // ─────────────────── Supplier name ───────────────────

    private function extractSupplierName(string $text): ?string
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => strlen($l) > 3
        ));

        // Priority 1: first line (in first 30) with a PT legal entity suffix that looks legit
        foreach (array_slice($lines, 0, 30) as $line) {
            if (strlen($line) > 120) {
                continue;
            }
            if (
                preg_match(self::PT_ENTITY_SUFFIXES, $line)
                && !preg_match(self::SKIP_LINE_RE, $line)
                && $this->looksLikeCompanyName($line)
            ) {
                return $this->trimToLastEntitySuffix($line);
            }
        }

        // Priority 2: full-text scan for entity suffix (may be past the first 30 lines)
        foreach ($lines as $line) {
            if (strlen($line) > 120) {
                continue;
            }
            if (
                preg_match(self::PT_ENTITY_SUFFIXES, $line)
                && !preg_match(self::SKIP_LINE_RE, $line)
                && $this->looksLikeCompanyName($line)
            ) {
                return $this->trimToLastEntitySuffix($line);
            }
        }

        // Priority 3: first non-trivial clean line
        foreach (array_slice($lines, 0, 8) as $line) {
            if (strlen($line) < 4 || strlen($line) > 120) {
                continue;
            }
            if (preg_match(self::SKIP_LINE_RE, $line)) {
                continue;
            }
            if (preg_match('/^\d+$/', $line) || preg_match('/^[A-Z]{1,4}$/', $line)) {
                continue;
            }
            if ($this->looksLikeCompanyName($line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Strip everything after the last PT legal entity suffix (e.g., "Lda.", "S.A.").
     * This removes trailing OCR garbage like "ATENEYA" that was added from a nearby column.
     */
    private function trimToLastEntitySuffix(string $line): string
    {
        if (preg_match_all(
            '/(?:Lda\.?|S\.?A\.?|SGPS|Unipessoal|Unip\.?|E\.?I\.?R\.?L\.?|S\.?R\.?L\.?)/iu',
            $line,
            $matches,
            PREG_OFFSET_CAPTURE
        )) {
            $last = end($matches[0]);
            $cutPos = $last[1] + strlen($last[0]);
            // Allow a trailing dot (e.g. "Lda.")
            if (isset($line[$cutPos]) && $line[$cutPos] === '.') {
                $cutPos++;
            }
            return trim(substr($line, 0, $cutPos));
        }
        return $line;
    }

    /**
     * Heuristic: a company name line should have at least one word of 4+ letters
     * and fewer than 15% noise/garbage characters.
     */
    private function looksLikeCompanyName(string $line): bool
    {
        // At least 2 words of 4+ letters (rules out "Yo Mah À FE ee My) Sy Pino SA" type garbage)
        preg_match_all('/[\p{L}]{4,}/u', $line, $longWords);
        if (count($longWords[0]) < 2) {
            return false;
        }
        // Fewer than 15% noise/garbage characters
        preg_match_all('/[^\p{L}\p{N}\s,.\-&\'\/ºª°()]/u', $line, $noise);
        $total = mb_strlen($line, 'UTF-8');
        return $total > 0 && (count($noise[0]) / $total) < 0.15;
    }

    // ─────────────────── Confidence ───────────────────

    private function buildConfidence(array $data): array
    {
        return [
            'supplier_name'       => $data['supplier_name'] !== null ? 45 : 0,
            'supplier_tax_number' => $data['supplier_tax_number'] !== null ? 90 : 0,
            'invoice_number'      => $data['invoice_number'] !== null ? 80 : 0,
            'invoice_date'        => $data['invoice_date'] !== null ? 75 : 0,
            'due_date'            => $data['due_date'] !== null ? 75 : 0,
            'subtotal'            => $data['subtotal'] !== null ? 70 : 0,
            'tax_total'           => $data['tax_total'] !== null ? 70 : 0,
            'total'               => $data['total'] !== null ? 80 : 0,
            'atcud'               => $data['atcud'] !== null ? 95 : 0,
            'items'               => 25,
        ];
    }
}
