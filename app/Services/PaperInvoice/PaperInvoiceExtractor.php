<?php

namespace App\Services\PaperInvoice;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PaperInvoiceExtractor
{
    public function extract(string $documentPath): array
    {
        $warnings = [];
        $extension = strtolower(pathinfo($documentPath, PATHINFO_EXTENSION));

        if ($extension === 'pdf') {
            [$rawText, $qrData] = $this->extractPdf($documentPath, $warnings);
        } else {
            $qrData = $this->readQrCode($documentPath, $warnings);
            $rawText = $this->runOcr($documentPath, $warnings);
        }

        return $this->parseText($rawText, $qrData, $warnings);
    }

    public function parseText(string $rawText, ?string $qrData = null, array $warnings = []): array
    {
        $lines = collect(preg_split('/\R/u', $rawText) ?: [])
            ->map(fn (string $line) => trim(preg_replace('/\s+/u', ' ', $line) ?? ''))
            ->filter()
            ->values();

        $products = $this->extractProducts($lines->all());
        $total = $this->extractTotal($rawText);
        $vatTotal = $this->extractVatTotal($rawText);
        $lineTotal = array_sum(array_column($products, 'lineTotal'));
        $qrFields = $this->parseQrFields($qrData);

        if ($rawText === '') {
            $warnings[] = 'OCR nao devolveu texto legivel.';
        }

        if ($products === []) {
            $warnings[] = 'Nao foram encontradas linhas de produtos.';
        }

        if ($total > 0 && $lineTotal > 0 && abs($total - $lineTotal) > 0.05) {
            $warnings[] = 'A soma das linhas nao coincide com o total da fatura.';
        }

        $confidence = $this->confidence($rawText, $products, $total, $warnings);

        return [
            'source' => 'paper_invoice_photo',
            'supplier' => [
                'name' => $this->extractSupplierName($lines->all()),
                'taxNumber' => $qrFields['supplier_nif'] ?: $this->extractTaxNumber($rawText),
            ],
            'invoice' => [
                'number' => $qrFields['invoice_number'] ?: $this->extractInvoiceNumber($rawText),
                'date' => $qrFields['date'] ?: $this->extractDate($rawText),
                'total' => $qrFields['total'] ?: $total,
                'vatTotal' => $qrFields['vat_total'] ?: $vatTotal,
                'currency' => 'EUR',
                'atcud' => $qrFields['atcud'],
                'type' => $qrFields['type'],
            ],
            'products' => $products,
            'confidence' => $confidence,
            'needsManualReview' => $confidence < 0.75 || $warnings !== [],
            'rawText' => $rawText,
            'qrData' => $qrData,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function extractPdf(string $pdfPath, array &$warnings): array
    {
        $text = '';
        $qrData = null;

        $pdfToText = $this->commandPath('pdftotext');

        if ($pdfToText) {
            try {
                $process = new Process([$pdfToText, '-layout', $pdfPath, '-']);
                $process->setTimeout(60)->mustRun();
                $text = trim($process->getOutput());
            } catch (ProcessFailedException|\Throwable $e) {
                $warnings[] = 'Falha ao extrair texto do PDF: '.$e->getMessage();
            }
        } else {
            $warnings[] = 'Leitor PDF local indisponivel: instala Poppler/pdftotext para ler PDFs.'.$this->toolingHint();
        }

        // Sem camada de texto o PDF e' uma digitalizacao: e' preciso OCR.
        $precisaOcr = ($text === '');

        // O QR e' lido SEMPRE, haja texto ou nao. Antes so se tentava quando o
        // pdftotext nao devolvia nada — ou seja, nunca nas facturas que vem por
        // email, que sao justamente as que trazem QR legivel. E o QR e' a unica
        // fonte exacta do NIF (campo A), do numero (G) e do ATCUD (H); tudo o
        // resto e' adivinhar a partir da disposicao do texto.
        $pdfToPpm = $this->commandPath('pdftoppm');

        if (! $pdfToPpm) {
            $warnings[] = $precisaOcr
                ? 'PDF sem texto legivel e pdftoppm indisponivel para converter paginas em imagem.'.$this->toolingHint()
                : 'pdftoppm indisponivel: nao foi possivel ler o QR code, os campos vieram do texto e podem estar errados.'.$this->toolingHint();

            return [$text, $qrData];
        }

        $tmpDir = storage_path('app/paper-invoices/pdf-pages/'.uniqid('pdf_', true));
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $prefix = $tmpDir.DIRECTORY_SEPARATOR.'page';

        try {
            $process = new Process([$pdfToPpm, '-png', '-f', '1', '-l', '3', $pdfPath, $prefix]);
            $process->setTimeout(90)->mustRun();

            foreach (glob($tmpDir.DIRECTORY_SEPARATOR.'*.png') ?: [] as $imagePath) {
                $qrData ??= $this->readQrCode($imagePath, $warnings, reportMissing: false);

                if ($precisaOcr) {
                    $text .= "\n".$this->runOcr($imagePath, $warnings);
                } elseif ($qrData !== null) {
                    break; // ja temos texto e QR, nao ha nada a ganhar nas paginas seguintes
                }
            }

            if ($qrData === null) {
                $warnings[] = 'QR code nao encontrado no PDF: os campos foram lidos do texto e convem conferir.';
            }
        } catch (ProcessFailedException|\Throwable $e) {
            $warnings[] = 'Falha ao converter PDF para OCR: '.$e->getMessage();
        } finally {
            foreach (glob($tmpDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($tmpDir);
        }

        return [trim($text), $qrData];
    }

    private function readQrCode(string $imagePath, array &$warnings, bool $reportMissing = true): ?string
    {
        $zbarImg = $this->commandPath('zbarimg');

        if (! $zbarImg) {
            $warnings[] = 'Leitor QR local indisponivel: instala zbarimg para ler QR codes.'.$this->toolingHint();
            return null;
        }

        try {
            $process = new Process([$zbarImg, '--raw', $imagePath]);
            $process->setTimeout(20)->mustRun();

            return trim($process->getOutput()) ?: null;
        } catch (ProcessFailedException|\Throwable) {
            // Ao varrer varias paginas so uma costuma ter o QR: avisar por
            // pagina encheria as Notas de ruido. Quem chama avisa uma vez.
            if ($reportMissing) {
                $warnings[] = 'QR code nao encontrado ou nao legivel.';
            }

            return null;
        }
    }

    private function runOcr(string $imagePath, array &$warnings): string
    {
        $tesseract = $this->commandPath('tesseract');

        if (! $tesseract) {
            $warnings[] = 'OCR local indisponivel: instala Tesseract para extrair texto.'.$this->toolingHint();
            return '';
        }

        $tessdataDir = $this->tessdataDir();

        foreach (array_unique([env('TESSERACT_LANGUAGE', 'por+eng'), 'por+eng', 'eng']) as $language) {
            try {
                $command = [$tesseract, $imagePath, 'stdout', '-l', $language, '--psm', '6'];
                if ($tessdataDir) {
                    array_splice($command, 3, 0, ['--tessdata-dir', $tessdataDir]);
                }

                $process = new Process($command);
                $process->setTimeout(90)->mustRun();

                return trim($process->getOutput());
            } catch (ProcessFailedException|\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        try {
            $process = new Process([$tesseract, $imagePath, 'stdout', '--psm', '6']);
            $process->setTimeout(90)->mustRun();

            return trim($process->getOutput());
        } catch (ProcessFailedException|\Throwable $e) {
            $warnings[] = 'Falha ao executar OCR local: '.($lastError ?? $e->getMessage());
            return '';
        }
    }

    private function tessdataDir(): ?string
    {
        $configured = env('TESSDATA_PREFIX');
        if (is_string($configured) && is_dir($configured)) {
            return $configured;
        }

        $local = base_path('bin/tessdata');
        return is_dir($local) ? $local : null;
    }

    /**
     * Em Plesk e cPanel é vulgar o PHP da web correr com proc_open desactivado.
     * Nesse caso nenhum destes programas pode ser invocado, por muito bem
     * instalados que estejam — e dizer "instala o Poppler" manda a pessoa para
     * o caminho errado durante horas.
     */
    private function toolingHint(): string
    {
        if (! function_exists('proc_open')) {
            return ' ATENÇÃO: este PHP tem proc_open desactivado, por isso nenhum'
                .' programa externo pode ser executado — instalar não resolve.'
                .' Retira proc_open de disable_functions nas definições PHP do site.';
        }

        return '';
    }

    private function commandPath(string $command): ?string
    {
        static $paths = [];

        if (array_key_exists($command, $paths)) {
            return $paths[$command];
        }

        $configured = config('paper_invoice.binaries.'.$command);
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $paths[$command] = $configured;
        }

        foreach ($this->fallbackCommandPaths($command) as $path) {
            if (is_file($path)) {
                return $paths[$command] = $path;
            }
        }

        if (PHP_OS_FAMILY === 'Windows' && in_array($command, ['pdftotext', 'pdftoppm', 'tesseract', 'zbarimg'], true)) {
            return $paths[$command] = null;
        }

        $check = PHP_OS_FAMILY === 'Windows'
            ? new Process(['where', $command])
            : new Process(['which', $command]);

        try {
            $check->setTimeout(2)->run();
            if ($check->isSuccessful()) {
                $path = trim(strtok($check->getOutput(), PHP_EOL) ?: '');

                if ($path !== '') {
                    return $paths[$command] = $path;
                }
            }
        } catch (\Throwable) {
            //
        }

        return $paths[$command] = null;
    }

    private function fallbackCommandPaths(string $command): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $localAppData = rtrim((string) getenv('LOCALAPPDATA'), '\\/');
        $programFiles = rtrim((string) getenv('ProgramFiles'), '\\/');
        $programFilesX86 = rtrim((string) getenv('ProgramFiles(x86)'), '\\/');
        $projectPath = function_exists('base_path') && function_exists('app') && method_exists(app(), 'basePath')
            ? base_path()
            : getcwd();

        return match ($command) {
            'tesseract' => array_filter([
                $programFiles.'\\Tesseract-OCR\\tesseract.exe',
                $programFilesX86.'\\Tesseract-OCR\\tesseract.exe',
            ]),
            'pdftotext', 'pdftoppm' => array_merge(
                array_filter([
                    $projectPath.'\\bin\\poppler\\Library\\bin\\'.$command.'.exe',
                    $localAppData.'\\Microsoft\\WinGet\\Packages\\oschwartz10612.Poppler_Microsoft.Winget.Source_8wekyb3d8bbwe\\poppler-25.07.0\\Library\\bin\\'.$command.'.exe',
                    'C:\\laragon\\bin\\git\\mingw64\\bin\\'.$command.'.exe',
                ]),
            ),
            'zbarimg' => array_filter([
                $projectPath.'\\bin\\zbar\\bin\\zbarimg.exe',
                $programFiles.'\\ZBar\\bin\\zbarimg.exe',
                $programFilesX86.'\\ZBar\\bin\\zbarimg.exe',
            ]),
            default => [],
        };
    }

    private function extractProducts(array $lines): array
    {
        $products = [];
        $insideItems = false;
        $lines = $this->joinWrappedProductLines($lines);

        foreach ($lines as $line) {
            if (preg_match('/\b(referencia|referÃªncia|designacao|designaÃ§Ã£o|descricao|descri..o|artigo|produto|servico|serviÃ§o)\b.*\b(qtd|quantidade|preco|preÃ§o|valor|total)\b/iu', $line)) {
                $insideItems = true;
                continue;
            }

            if (preg_match('/\b(sub[- ]?total|total\s+documento|total\s+a\s+pagar|valor\s+total|iva\s+\d{1,2}|atcud)\b/iu', $line)) {
                $insideItems = false;
                continue;
            }

            if (preg_match('/^(?:ref\.?|sku|cod\.?|descricao|descri..o|artigo|produto|servico|qtd|quantidade|preco|valor|iva|taxa)(\s|$)/iu', $line)
                && ! preg_match('/\d+[,.]\d{2}/u', $line)) {
                continue;
            }

            if (preg_match('/^(?:(?<ref>[A-Z0-9._\/-]{2,})\s+)?(?<description>.+?)\s+(?<quantity>\d+(?:[,.]\d+)?)\s*(?:x|un|uni|und|kg|lt)?\s+â‚¬?\s*(?<unit>\d+(?:[.\s]\d{3})*[,.]\d{2,4})\s+(?<vat>\d{1,2}(?:[,.]\d{1,2})?)\s*%?\s+â‚¬?\s*(?<total>\d+(?:[.\s]\d{3})*[,.]\d{2})$/iu', $line, $matches)) {
                $products[] = [
                    'description' => $this->cleanProductDescription(($matches['ref'] ?? '').' '.$matches['description']),
                    'quantity' => $this->moneyToFloat($matches['quantity']),
                    'unitPrice' => $this->moneyToFloat($matches['unit']),
                    'vatRate' => $this->moneyToFloat($matches['vat']),
                    'lineTotal' => $this->moneyToFloat($matches['total']),
                    'confidence' => 0.75,
                ];
                continue;
            }

            if (preg_match('/^(?:(?<ref>[A-Z0-9._\/-]{2,})\s+)?(?<description>.+?)\s+(?<quantity>\d+(?:[,.]\d+)?)\s+â‚¬?\s*(?<unit>\d+(?:[.\s]\d{3})*[,.]\d{2,4})\s+â‚¬?\s*(?<total>\d+(?:[.\s]\d{3})*[,.]\d{2})$/u', $line, $matches)) {
                $products[] = [
                    'description' => $this->cleanProductDescription(($matches['ref'] ?? '').' '.$matches['description']),
                    'quantity' => $this->moneyToFloat($matches['quantity']),
                    'unitPrice' => $this->moneyToFloat($matches['unit']),
                    'vatRate' => 0,
                    'lineTotal' => $this->moneyToFloat($matches['total']),
                    'confidence' => 0.65,
                ];
                continue;
            }

            if ($structuredProduct = $this->extractStructuredProductLine($line)) {
                $products[] = $structuredProduct;
                continue;
            }
            if (! $insideItems) {
                continue;
            }

            preg_match_all('/\d+(?:[,.]\d+)?/u', $line, $numberMatches);
            if (preg_match('/%/u', $line) || count($numberMatches[0]) >= 3) {
                continue;
            }

            if (preg_match('/^(.{4,}?)\s+(\d+(?:[.\s]\d{3})*[,.]\d{2})$/u', $line, $simpleMatches)) {
                $description = trim($simpleMatches[1]);

                if (preg_match('/^(subtotal|total|iva|imposto|troco|desconto|base tributavel|atcud)\b/iu', $description)) {
                    continue;
                }

                $products[] = [
                    'description' => $this->cleanProductDescription($description),
                    'quantity' => 1,
                    'unitPrice' => $this->moneyToFloat($simpleMatches[2]),
                    'vatRate' => 0,
                    'lineTotal' => $this->moneyToFloat($simpleMatches[2]),
                    'confidence' => 0.45,
                ];
            }
        }

        return $products;
    }

    private function joinWrappedProductLines(array $lines): array
    {
        $joined = [];
        $current = null;

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $startsProduct = preg_match('/^(?:[^\w]{0,8}\s*)?\d{7,8}[\]\)!|]?\s+[A-Z0-9?]{2,}/iu', $line);
            $looksLikeContinuation = $current !== null
                && ! preg_match('/\b(total|subtotal|mercadorias|atcud|iban|transfer[eê]ncia|dados\s+para)\b/iu', $line);

            if ($startsProduct) {
                if ($current !== null) {
                    $joined[] = $current;
                }

                $current = $line;
                continue;
            }

            if ($looksLikeContinuation) {
                $current .= ' '.$line;
                continue;
            }

            if ($current !== null) {
                $joined[] = $current;
                $current = null;
            }

            $joined[] = $line;
        }

        if ($current !== null) {
            $joined[] = $current;
        }

        $split = [];
        foreach ($joined as $line) {
            foreach (preg_split('/(?=(?:[^\w]{0,8}\s*)?\d{7,8}[\]\)!|]?\s*[|!]?\s*[A-Z0-9?]{2,})/u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $chunk) {
                $split[] = trim($chunk);
            }
        }

        return $split;
    }

    private function extractStructuredProductLine(string $line): ?array
    {
        $normalized = str_replace(['|', ']', '[', ')', '('], ' ', $line);
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        if (! preg_match('/\d{7,8}/u', $normalized) || ! preg_match('/\d{1,2}\s*%/u', $normalized)) {
            return null;
        }

        if (! preg_match('/(?<quantity>\d+(?:[,.]\d{2})?)\s+(?<unitPrice>\d+(?:[,.]\d{2})?)\s+(?<vat>\d{1,2})\s*%?\s+(?<total>\d+(?:[.\s]\d{3})*[,.]\d{2})(?:\s|$)/u', $normalized, $matches)) {
            return null;
        }

        if (preg_match('/\d{7,8}/u', $normalized, $dateMatch, PREG_OFFSET_CAPTURE)) {
            $normalized = substr($normalized, $dateMatch[0][1]);
        }

        $prefix = trim(substr($normalized, 0, (int) strpos($normalized, $matches[0])));
        $prefix = preg_replace('/^\d{7,8}\s*/u', '', $prefix) ?? $prefix;
        $prefix = preg_replace('/^[|!]?\s*[A-Z0-9?]{2,}\s*/u', '', $prefix) ?? $prefix;
        $description = preg_replace('/\b(KG|KLG|UN|UNI)\b.*$/iu', '', $prefix) ?? $prefix;
        $description = preg_replace('/\s+\d+[,.]\d{2}\s+\d+\s+\d+(?:\s+\d+[,.]\d{2})?.*$/u', '', $description) ?? $description;
        $description = preg_replace('/\s+\d{3,}\s+(?:oo|o0|0o|0{2,3})\s+0{2,3}.*$/iu', '', $description) ?? $description;

        if (mb_strlen(trim($description)) < 3) {
            return null;
        }

        $unitPrice = $this->normalizeUnitPrice($matches['unitPrice'], $matches['quantity'], $matches['total']);

        return [
            'description' => $this->cleanProductDescription($description),
            'quantity' => $this->normalizeQuantity($matches['quantity'], (string) $unitPrice, $matches['total']),
            'unitPrice' => $unitPrice,
            'vatRate' => $this->moneyToFloat($matches['vat']),
            'lineTotal' => $this->moneyToFloat($matches['total']),
            'confidence' => 0.85,
        ];
    }

    private function normalizeUnitPrice(string $unitPrice, string $quantity, string $lineTotal): float
    {
        $unit = $this->moneyToFloat($unitPrice);
        $qty = $this->moneyToFloat($quantity);
        $total = $this->moneyToFloat($lineTotal);

        if ($unit >= 10 && $qty > 0 && $total > 0) {
            foreach ([100, 10] as $divisor) {
                $candidate = $unit / $divisor;
                if (abs(($candidate * $qty) - $total) < 0.25 || abs(($candidate * ($qty / 100)) - $total) < 0.25) {
                    return round($candidate, 2);
                }
            }
        }

        return $unit;
    }

    private function normalizeQuantity(string $quantity, string $unitPrice, string $lineTotal): float
    {
        $qty = $this->moneyToFloat($quantity);
        $unit = $this->moneyToFloat($unitPrice);
        $total = $this->moneyToFloat($lineTotal);

        if ($qty >= 100 && $unit > 0 && $total > 0) {
            $expected = $total / $unit;
            if (abs(($qty / 100) - $expected) < 0.2) {
                return round($qty / 100, 2);
            }
        }

        if (! str_contains($quantity, ',') && ! str_contains($quantity, '.') && $qty >= 100 && ((int) $qty) % 100 === 0) {
            return round($qty / 100, 2);
        }

        return $qty;
    }

    private function cleanProductDescription(string $description): string
    {
        $description = trim(preg_replace('/\s+/u', ' ', $description) ?? $description);
        $description = preg_replace('/^[|:;,\-\s]+/u', '', $description) ?? $description;

        return mb_substr($description, 0, 255);
    }

    private function extractSupplierName(array $lines): string
    {
        $ignored = '/^(original|duplicado|exmo\.?\s*srs?\.?|v\/?\s*refer[eÃª]ncia|refer[eÃª]ncia|data|cid|v\/?\s*contribuinte)$/iu';

        // A forma juridica tem de ser um termo por si so. Sem isto, o "SA" da
        // alternativa casava com o "ssa" de "Remessa" e a linha
        // "Guia(s) de Remessa:" devolvia "de Remessa" como nome do fornecedor.
        // O parentese nao pertence a classe de caracteres, por isso a captura
        // comecava a meio da linha. Visto numa factura da PRIO em 29/08/2026.
        $formaJuridica = '(?:Unipessoal(?:\s+Lda\.?)?|Unip\.?|Lda\.?|Limitada|S\.?\s?A\.?|SGPS|ACE)';
        $naoEOFornecedor = '/\b(cliente|exmo|atenea|ateneya|nif|morada|sentido\s+da\s+fruta\s+-)\b/iu';

        foreach ($lines as $line) {
            // O sufixo tem de vir precedido de espaco ou virgula e nao pode ter
            // uma letra a seguir — e' assim que deixa de casar dentro de palavras.
            if (preg_match('/([\p{L}0-9 .,&-]{2,70}?[,\s]+'.$formaJuridica.')(?![\p{L}])/iu', $line, $matches)
                && ! preg_match($naoEOFornecedor, $matches[1])) {
                // Nao se tira o ponto final: faz parte de "S.A." e do nome legal.
                return trim($matches[1], " \t,");
            }
        }

        foreach ($lines as $line) {
            if (! preg_match($ignored, $line) && ! preg_match('/(fatura|factura|recibo|nif|contribuinte|total|refer[eÃª]ncia|designa[cÃ§][aÃ£]o)/iu', $line)) {
                return $line;
            }
        }

        return '';
    }

    private function extractTaxNumber(string $text): string
    {
        if (preg_match('/(?<!V\/\s)Contribuinte\s*N[Âººo]?\s*(\d{9})/iu', $text, $matches)) {
            return $matches[1];
        }

        return preg_match('/(?:NIF|Contribuinte|NIPC|N\.?\s*Fiscal|VAT)\D*(\d{9})/iu', $text, $matches)
            ? $matches[1]
            : '';
    }

    private function extractInvoiceNumber(string $text): string
    {
        $strictPatterns = [
            '/\bN[Âººo]?\s*(FAC\s+[A-Z0-9._\/-]+)/iu',
            '/(?:Fatura-recibo|Factura-recibo)\s*[:#]?\s*([^\r\n]+)/iu',
            '/(?:Fatura|Factura|Fatura-recibo|Factura-recibo)\s*[:#]?\s*((?:FAC|FT|FS|FR|NC|ND|RC)?\s*[A-Z0-9._\/-]+(?:\s+[A-Z0-9._\/-]+)?)/iu',
            '/(?:Documento|Doc\.?)\s*(?:n\.?|nÂº|nÃ‚Âº|numero|nÃºmero|nÃƒÂºmero)?\s*[:#-]?\s*([A-Z0-9._\/-]{3,40})/iu',
            '/\b((?:FAC|FT|FS|FR|NC|ND|RC)\s+[A-Z0-9._\/-]{3,40})/iu',
        ];

        foreach ($strictPatterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim(preg_replace('/\s+/u', ' ', $matches[1] ?? $matches[0]) ?? '');
            }
        }

        $patterns = [
            '/(?:Fatura|Factura|Documento)\s*(?:n\.?|nÂº|numero|nÃºmero)?\D*([A-Z0-9\/\-. ]{3,40})/iu',
            '/\b(FT|FS|FR|NC|ND)\s+[A-Z0-9\/\-. ]{3,40}/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1] ?? $matches[0]);
            }
        }

        return '';
    }

    private function extractDate(string $text): string
    {
        if (preg_match('/(\d{2}[\/\-]\d{2}[\/\-]\d{4})/u', $text, $matches)) {
            return str_replace('-', '/', $matches[1]);
        }

        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/u', $text, $matches)) {
            return $matches[3].'/'.$matches[2].'/'.$matches[1];
        }

        return '';
    }

    private function extractTotal(string $text): float
    {
        if (preg_match_all('/(?:total\s+a\s+pagar|valor\s+a\s+pagar|total\s+documento|total\s+liquido|total\s+l[iÃ­]quido)\D+(\d{1,6}(?:[.\s]\d{3})*[,.]\d{2})/iu', $text, $matches)) {
            return $this->moneyToFloat(end($matches[1]));
        }

        return 0.0;
    }

    private function extractVatTotal(string $text): float
    {
        if (! preg_match('/(?:total\s+de\s+i\.?\s*v\.?\s*a\.?|total\s+iva|valor\s+de\s+i\.?\s*v\.?\s*a\.?)\D+(\d{1,6}(?:[.\s]\d{3})*[,.]\d{2})/iu', $text, $matches)) {
            return 0.0;
        }

        $vat = $this->moneyToFloat($matches[1]);
        $total = $this->extractTotal($text);

        return $total > 0 && $vat > ($total * 0.35) ? 0.0 : $vat;
    }

    private function confidence(string $rawText, array $products, float $total, array $warnings): float
    {
        $score = 0.2;
        $score += $rawText !== '' ? 0.25 : 0;
        $score += $products !== [] ? 0.25 : 0;
        $score += $total > 0 ? 0.2 : 0;
        $score -= min(0.3, count($warnings) * 0.08);

        return round(max(0, min(1, $score)), 2);
    }

    private function moneyToFloat(string $value): float
    {
        $clean = preg_replace('/[^\d,.-]/', '', $value) ?? '0';

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
        }

        return round((float) str_replace(',', '.', $clean), 2);
    }

    private function parseQrFields(?string $qrData): array
    {
        $empty = [
            'supplier_nif' => '',
            'invoice_number' => '',
            'date' => '',
            'total' => 0.0,
            'vat_total' => 0.0,
            'atcud' => '',
            'type' => '',
        ];

        if (! $qrData) {
            return $empty;
        }

        $fields = [];
        foreach (explode('*', $qrData) as $pair) {
            $index = strpos($pair, ':');
            if ($index === false) {
                continue;
            }
            $fields[trim(substr($pair, 0, $index))] = trim(substr($pair, $index + 1));
        }

        return [
            'supplier_nif' => $fields['A'] ?? '',
            'invoice_number' => $fields['G'] ?? '',
            'date' => $this->qrDate($fields['F'] ?? ''),
            'total' => isset($fields['O']) ? $this->moneyToFloat($fields['O']) : 0.0,
            'vat_total' => isset($fields['N']) ? $this->moneyToFloat($fields['N']) : 0.0,
            'atcud' => $fields['H'] ?? '',
            'type' => $fields['D'] ?? '',
        ];
    }

    private function qrDate(string $value): string
    {
        if (! preg_match('/^\d{8}$/', $value)) {
            return '';
        }

        return substr($value, 6, 2).'/'.substr($value, 4, 2).'/'.substr($value, 0, 4);
    }
}

