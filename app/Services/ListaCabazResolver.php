<?php

namespace App\Services;

use App\Models\ListaCabaz;
use App\Models\ListaCabazItem;
use App\Models\WooOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ponto unico para saber o que leva um cabaz de subscricao.
 *
 * As listas semanais (Listas de cabazes) dizem que produtos e que quantidades
 * levam o mini, o pequeno, o medio e o grande. Este resolver escolhe a lista
 * que se aplica a uma data e devolve as linhas ja prontas a usar, para que a
 * preparacao B2C, as compras, a fatura da subscricao e as margens leiam todas
 * a mesma coisa.
 */
class ListaCabazResolver
{
    /** Listas ja resolvidas nesta execucao, por ano-semana. */
    private array $cache = [];

    /**
     * A lista que manda numa data: a da mesma semana ISO, senao a ultima
     * publicada ate essa semana, senao a ultima publicada que existir.
     */
    public function listaParaData(Carbon|string $data): ?ListaCabaz
    {
        $data = $data instanceof Carbon ? $data : Carbon::parse($data);
        $chave = $data->isoWeekYear.'-'.$data->isoWeek;

        if (array_key_exists($chave, $this->cache)) {
            return $this->cache[$chave];
        }

        $ano = (int) $data->isoWeekYear;
        $semana = (int) $data->isoWeek;

        $publicadas = ListaCabaz::query()->publicada()->with('itens');

        $lista = (clone $publicadas)
            ->where('ano', $ano)
            ->where('semana_numero', $semana)
            ->first();

        // Enquanto nao houver lista da semana, vale a ultima publicada antes
        // dela — a composicao mantem-se de semana para semana.
        $lista ??= (clone $publicadas)
            ->where(fn ($query) => $query
                ->where('ano', '<', $ano)
                ->orWhere(fn ($query) => $query->where('ano', $ano)->where('semana_numero', '<=', $semana)))
            ->orderByDesc('ano')
            ->orderByDesc('semana_numero')
            ->first();

        // Listas so do futuro (caso tipico logo depois de criar a primeira).
        $lista ??= (clone $publicadas)
            ->orderBy('ano')
            ->orderBy('semana_numero')
            ->first();

        return $this->cache[$chave] = $lista;
    }

    /**
     * As linhas do tamanho pedido para essa data.
     *
     * @return Collection<int, ListaCabazItem>
     */
    public function itens(?string $tipo, Carbon|string $data): Collection
    {
        if (blank($tipo)) {
            return collect();
        }

        $lista = $this->listaParaData($data);

        if ($lista === null) {
            return collect();
        }

        return $lista->itens
            ->where('cabaz_tipo', $tipo)
            ->sortBy([['ordem', 'asc'], ['produto', 'asc']])
            ->values();
    }

    /**
     * O tamanho que uma encomenda assina: o campo da ficha, senao adivinhado
     * pelo nome dos produtos da encomenda.
     */
    public function tipoDaEncomenda(WooOrder $order): ?string
    {
        if (filled($order->cabaz_tipo)) {
            return $order->cabaz_tipo;
        }

        return WooOrder::detectarCabazTipo($order->line_items ?? []);
    }

    /**
     * A lista de picagem de uma encomenda B2C numa data: as linhas da
     * composicao do cabaz mais os produtos extra que o cliente pediu.
     *
     * Cada linha traz uma chave estavel (guardada em produtos_picados), o
     * texto a mostrar e se o cliente excluiu esse produto.
     *
     * @return array{tipo: ?string, lista: ?ListaCabaz, linhas: array<int, array<string, mixed>>}
     */
    public function picagemB2c(WooOrder $order, Carbon|string $data): array
    {
        $tipo = $this->tipoDaEncomenda($order);
        $lista = $tipo !== null ? $this->listaParaData($data) : null;
        $itens = $this->itens($tipo, $data);
        $excluidos = $this->excluidos($order);

        $linhas = [];

        foreach ($itens as $item) {
            $linhas[] = [
                'chave' => 'lista-'.$item->id,
                'texto' => $this->textoQuantidade($item).' '.$item->produto,
                'produto' => $item->produto,
                'categoria' => $item->categoria,
                'quantidade' => (float) $item->quantidade,
                'unidade' => $item->unidade,
                'origem' => 'lista',
                'excluido' => $this->estaExcluido($item->produto, $excluidos),
            ];
        }

        // Produtos que nao sao o cabaz (extras que o cliente comprou) continuam
        // a ser picados um a um.
        foreach ($order->line_items ?? [] as $indice => $produto) {
            $nome = (string) ($produto['name'] ?? 'Produto');

            if ($itens->isNotEmpty() && $this->ehOCabaz($nome, $tipo)) {
                continue;
            }

            $linhas[] = [
                'chave' => 'woo-'.$indice,
                'texto' => ((int) ($produto['quantity'] ?? 0)).'x '.$nome,
                'produto' => $nome,
                'categoria' => null,
                'quantidade' => (float) ($produto['quantity'] ?? 0),
                'unidade' => 'un',
                'origem' => 'encomenda',
                'excluido' => false,
            ];
        }

        return ['tipo' => $tipo, 'lista' => $lista, 'linhas' => $linhas];
    }

    /** "250 gr", "1,5 kg", "3x". */
    public function textoQuantidade(ListaCabazItem $item): string
    {
        $quantidade = (float) $item->quantidade;
        $unidade = mb_strtolower(trim((string) $item->unidade));
        $numero = rtrim(rtrim(number_format($quantidade, 3, ',', ''), '0'), ',');

        if (in_array($unidade, ['un', 'uni', 'unidade', 'unidades', ''], true)) {
            return $numero.'x';
        }

        return $numero.' '.$item->unidade;
    }

    /** Quantidade da linha convertida para kg, quando faz sentido. */
    public function quantidadeKg(ListaCabazItem $item): ?float
    {
        return $item->quantidadeParaCustoKg();
    }

    /** @return array<int, string> */
    private function excluidos(WooOrder $order): array
    {
        $lista = collect($order->excluded_products ?? []);

        if (filled($order->preferences_text)) {
            $lista = $lista->merge(preg_split('/[\n,;]+/', (string) $order->preferences_text) ?: []);
        }

        return $lista
            ->map(fn ($texto): string => $this->normalizar((string) $texto))
            ->filter(fn (string $texto): bool => mb_strlen($texto) >= 3)
            ->unique()
            ->values()
            ->all();
    }

    /** @param array<int, string> $excluidos */
    private function estaExcluido(string $produto, array $excluidos): bool
    {
        $alvo = $this->normalizar($produto);

        foreach ($excluidos as $excluido) {
            if (str_contains($alvo, $excluido) || str_contains($excluido, $alvo)) {
                return true;
            }
        }

        return false;
    }

    /** A linha da encomenda que e o proprio cabaz (ja coberta pela composicao). */
    private function ehOCabaz(string $nome, ?string $tipo): bool
    {
        $nomeNormalizado = $this->normalizar($nome);

        if (! str_contains($nomeNormalizado, 'cabaz')) {
            return false;
        }

        return $tipo === null || WooOrder::detectarCabazTipo([['name' => $nome]]) === $tipo;
    }

    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ç' => 'c',
        ]);

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/', ' ', $texto)) ?? '');
    }
}
