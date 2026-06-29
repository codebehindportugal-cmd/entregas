<?php

namespace App\Services;

use Smalot\PdfParser\Parser;

class PdfProductExtractor
{
    public function extractFromPath(string $absolutePath): array
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($absolutePath);
            $text = $pdf->getText();
        } catch (\Throwable) {
            return [];
        }

        return $this->parseText($text);
    }

    private function parseText(string $text): array
    {
        // Normalise: vírgula decimal → ponto; remover espaços múltiplos
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $lines = preg_split('/\r?\n/', $text);

        $products = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) < 5) {
                continue;
            }

            // Tentar extrair produto de linhas de tabela de fatura portuguesa.
            // Formato mais comum (Makro, Metro, distribuidores):
            //   Descricao   Qtd   Un   Preco s/IVA   IVA%   Total
            // Ou:
            //   Descricao   Qtd   Un   PrecoUnit   Total
            $product = $this->matchInvoiceLine($line);
            if ($product !== null) {
                $products[] = $product;
            }
        }

        // Se não encontrámos nada com regex de linha, tentar abordagem por tokens
        if (empty($products)) {
            $products = $this->fallbackTokenExtract($lines);
        }

        return $products;
    }

    private function matchInvoiceLine(string $line): ?array
    {
        // Padrão: "Descricao [ref] qtd unidade preco_unit [iva%] total"
        // Exemplos:
        //  Alface Frisada 5 KG 0,80 6% 4,24
        //  TOMATE REDONDO 10.500 KG 1,2500 6 13,12
        //  001 Cenoura Baby 2 SC 15,00 6% 31,80

        // Regex genérico: texto + pelo menos 2 números no final
        // Captura: (descricao)(qtd)(unidade?)(preco_unit)(iva?)(total)
        $pattern = '/^'
            . '(?:\d+\s+)?'                                   // código opcional (ex: "001 ")
            . '([A-Za-zÀ-ÿ][^\d]{3,60?}?)\s+'               // descrição (começa por letra)
            . '(\d+[.,]?\d{0,3})\s+'                         // quantidade
            . '([A-Za-z]{1,5}\s+)?'                          // unidade opcional
            . '(\d+[.,]\d{2,4})\s+'                          // preço unitário
            . '(?:(\d{1,2})[%]?\s+)?'                        // IVA opcional
            . '(\d+[.,]\d{2})'                               // total
            . '\s*$/u';

        if (!preg_match($pattern, $line, $m)) {
            return null;
        }

        $descricao = trim($m[1]);
        // Ignorar linhas que são cabeçalhos ou totais
        if ($this->isHeaderOrFooter($descricao)) {
            return null;
        }

        $quantidade = $this->parseNumber($m[2]);
        if ($quantidade <= 0) {
            return null;
        }

        $precoUnit = $this->parseNumber($m[4]);
        if ($precoUnit <= 0) {
            return null;
        }

        // Verificar coerência: qtd * preco ≈ total (±10%)
        $totalLinha = $this->parseNumber($m[6]);
        $calculado = $quantidade * $precoUnit;
        if ($totalLinha > 0 && abs($calculado - $totalLinha) / $totalLinha > 0.15) {
            // pode ser que o preço já inclui IVA; tentar sem IVA
            return null;
        }

        $unidade = isset($m[3]) ? trim($m[3]) : 'un';
        if (!$unidade) {
            $unidade = 'un';
        }

        $iva = isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 6;
        if (!in_array($iva, [0, 6, 13, 23])) {
            $iva = 6;
        }

        return [
            'descricao'            => $descricao,
            'quantidade'           => $quantidade,
            'unidade_compra'       => strtolower($unidade),
            'unidades_por_quantidade' => 1,
            'quantidade_unidades'  => $quantidade,
            'preco_unitario'       => $precoUnit,
            'iva_percentagem'      => $iva,
            'notas'                => null,
        ];
    }

    private function fallbackTokenExtract(array $lines): array
    {
        // Estratégia alternativa: juntar linha de descrição com a linha seguinte de valores
        $products = [];
        $n = count($lines);

        for ($i = 0; $i < $n - 1; $i++) {
            $desc = trim($lines[$i]);
            $vals = trim($lines[$i + 1]);

            if (strlen($desc) < 3 || !preg_match('/[A-Za-zÀ-ÿ]{3}/', $desc)) {
                continue;
            }
            if ($this->isHeaderOrFooter($desc)) {
                continue;
            }

            // A linha seguinte deve ter pelo menos 2 números
            preg_match_all('/\d+[.,]\d+/', $vals, $nums);
            if (count($nums[0]) < 2) {
                continue;
            }

            $quantidade = $this->parseNumber($nums[0][0]);
            $preco = $this->parseNumber($nums[0][1]);

            if ($quantidade <= 0 || $preco <= 0 || $quantidade > 99999 || $preco > 9999) {
                continue;
            }

            $products[] = [
                'descricao'               => $desc,
                'quantidade'              => $quantidade,
                'unidade_compra'          => 'un',
                'unidades_por_quantidade' => 1,
                'quantidade_unidades'     => $quantidade,
                'preco_unitario'          => $preco,
                'iva_percentagem'         => 6,
                'notas'                   => null,
            ];

            $i++; // saltar linha de valores
        }

        return $products;
    }

    private function parseNumber(string $s): float
    {
        // "1.234,56" → 1234.56   |   "1,234.56" → 1234.56   |   "1,5" → 1.5
        $s = trim($s);
        // Se tem ponto e vírgula, o último separador é decimal
        if (str_contains($s, '.') && str_contains($s, ',')) {
            $lastDot   = strrpos($s, '.');
            $lastComma = strrpos($s, ',');
            if ($lastComma > $lastDot) {
                // formato europeu: 1.234,56
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                // formato americano: 1,234.56
                $s = str_replace(',', '', $s);
            }
        } else {
            // Só tem vírgula → decimal europeu
            $s = str_replace(',', '.', $s);
        }

        return (float) $s;
    }

    private function isHeaderOrFooter(string $text): bool
    {
        $lower = mb_strtolower($text);
        $keywords = [
            'descri', 'artigo', 'produto', 'total', 'subtotal', 'base',
            'iva', 'imposto', 'referenci', 'codigo', 'quant', 'preco',
            'valor', 'fatura', 'factura', 'cliente', 'fornecedor', 'data',
            'pagamento', 'vencimento', 'observa', 'nota', 'morada',
        ];
        foreach ($keywords as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }

        return false;
    }
}
