<?php

namespace App\Services;

use App\Models\WooProduct;
use Illuminate\Support\Str;

/**
 * Le o nome do produto e arrisca um palpite sobre como e que ele se vende.
 *
 * "Ameixa 500g" da embalagem de 0,5 kg; "Molho de couves" da unidade. O palpite
 * serve para nao ter de preencher 200 produtos a mao — mas nunca inventa o peso
 * medio de uma unidade, que e a unica coisa que faria uma encomenda sair errada
 * sem ninguem reparar. Fica sempre por confirmar (`unidades_confirmadas`) ate
 * alguem o validar no ecra dos produtos.
 */
class InferidorUnidadesProduto
{
    /** Palavras que so por si dizem que o produto se vende a peca. */
    private const PALAVRAS_UNIDADE = [
        'unidade', 'unidades', 'un', 'uni', 'peca', 'pecas', 'molho', 'molhos',
        'caixa', 'caixas', 'cuvete', 'cuvetes', 'saco', 'sacos', 'ramo', 'ramos',
        'cacho', 'cachos', 'embalagem', 'embalagens', 'pack', 'duzia', 'duzias',
    ];

    /**
     * @return array{unidade_venda: string, formato_qtd: float, formato_unidade: string, peso_medio_kg: float|null, confianca: string}
     */
    public function infer(WooProduct $produto): array
    {
        $texto = $this->normalizar($produto->name.' '.$produto->slug.' '.$produto->sku);
        $peso = $this->pesoNoTexto($texto);

        if ($peso !== null) {
            return [
                'unidade_venda' => 'peso',
                'formato_qtd' => $peso,
                'formato_unidade' => 'kg',
                'peso_medio_kg' => $produto->peso_medio_kg !== null ? (float) $produto->peso_medio_kg : null,
                'confianca' => 'alta',
            ];
        }

        $temPalavraUnidade = collect(self::PALAVRAS_UNIDADE)
            ->contains(fn (string $palavra): bool => Str::contains($texto, ' '.$palavra.' '));

        return [
            'unidade_venda' => 'unidade',
            'formato_qtd' => 1.0,
            'formato_unidade' => 'un',
            // Nunca se inventa: ou ja la estava, ou fica por preencher.
            'peso_medio_kg' => $produto->peso_medio_kg !== null ? (float) $produto->peso_medio_kg : null,
            'confianca' => $temPalavraUnidade ? 'alta' : 'baixa',
        ];
    }

    /** Aplica o palpite, a nao ser que alguem ja tenha confirmado o produto. */
    public function aplicar(WooProduct $produto, bool $force = false): bool
    {
        if ($produto->unidades_confirmadas && ! $force) {
            return false;
        }

        $inferido = $this->infer($produto);
        unset($inferido['confianca']);
        $produto->fill($inferido);

        return true;
    }

    /** Procura "500 g", "1,5kg", "2 quilos" e devolve o valor em kg. */
    private function pesoNoTexto(string $texto): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kgs?|quilos?|kilos?|g|gr|gramas?)\b/i', $texto, $m) !== 1) {
            return null;
        }

        $valor = (float) str_replace(',', '.', $m[1]);
        $unidade = strtolower($m[2]);

        if ($valor <= 0) {
            return null;
        }

        $emKg = Str::startsWith($unidade, ['k', 'q'])
            ? $valor
            : $valor / 1000;

        // "Maca 5" nao e peso nenhum; um formato acima de 25 kg tambem nao e cabaz.
        return $emKg > 0 && $emKg <= 25 ? round($emKg, 3) : null;
    }

    private function normalizar(?string $texto): string
    {
        $texto = Str::lower(Str::ascii((string) $texto));
        $texto = preg_replace('/[^a-z0-9,.]+/', ' ', $texto) ?? '';

        return ' '.trim(preg_replace('/\s+/', ' ', $texto) ?? '').' ';
    }
}
