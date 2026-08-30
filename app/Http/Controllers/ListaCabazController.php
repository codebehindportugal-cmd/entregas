<?php

namespace App\Http\Controllers;

use App\Models\ListaCabaz;
use App\Models\ListaCabazItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Listas semanais dos cabazes de subscricao: que produtos e que quantidades
 * levam o mini, o pequeno, o medio e o grande em cada semana.
 *
 * Alimenta a preparacao dos cabazes B2C, as necessidades de compra, a
 * composicao da fatura da subscricao e o custo/margem de cada cabaz.
 */
class ListaCabazController extends Controller
{
    /** Os quatro tamanhos, pela ordem em que se mostram. */
    public const TIPOS = [
        'mini' => 'Mini',
        'pequeno' => 'Pequeno',
        'medio' => 'Medio',
        'grande' => 'Grande',
    ];

    public function index(Request $request): View
    {
        $ano = (int) ($request->integer('ano') ?: now()->year);

        $listas = ListaCabaz::query()
            ->withCount('itens')
            ->where('ano', $ano)
            ->orderByDesc('mes')
            ->orderByDesc('semana_numero')
            ->get();

        $anos = ListaCabaz::query()
            ->select('ano')
            ->distinct()
            ->orderByDesc('ano')
            ->pluck('ano');

        if ($anos->doesntContain($ano)) {
            $anos = $anos->push($ano)->sortDesc()->values();
        }

        return view('lista-cabazes.index', [
            'listas' => $listas,
            'ano' => $ano,
            'anos' => $anos,
            'tipos' => self::TIPOS,
            'sugestao' => $this->sugestaoProximaSemana(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'semana_numero' => ['required', 'integer', 'min:1', 'max:53'],
            'ano' => ['required', 'integer', 'min:2020', 'max:2100'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'copiar_de' => ['nullable', 'integer', 'exists:lista_cabazes,id'],
        ]);

        $existente = ListaCabaz::query()
            ->where('semana_numero', $data['semana_numero'])
            ->where('ano', $data['ano'])
            ->where('mes', $data['mes'])
            ->first();

        if ($existente !== null) {
            return redirect()->route('lista-cabazes.edit', $existente)
                ->withErrors(['lista' => 'Ja existia uma lista para essa semana — abri essa.']);
        }

        $lista = ListaCabaz::create([
            'semana_numero' => $data['semana_numero'],
            'ano' => $data['ano'],
            'mes' => $data['mes'],
            'descricao' => $data['descricao'] ?? null,
            'estado' => 'rascunho',
        ]);

        // Copiar a composicao de outra semana poupa reescrever tudo.
        if (filled($data['copiar_de'] ?? null)) {
            $origem = ListaCabaz::with('itens')->find($data['copiar_de']);

            foreach ($origem?->itens ?? [] as $item) {
                $lista->itens()->create(collect($item->toArray())
                    ->only(['cabaz_tipo', 'produto', 'categoria', 'quantidade', 'unidade', 'peso_unitario_kg', 'tabela_preco_item_id', 'preco_unitario', 'ordem'])
                    ->all());
            }
        }

        return redirect()->route('lista-cabazes.edit', $lista)->with('status', 'Lista criada.');
    }

    public function edit(ListaCabaz $listaCabaz): View
    {
        $listaCabaz->load('itens');

        $itensPorTipo = collect(self::TIPOS)->mapWithKeys(fn (string $label, string $tipo): array => [
            $tipo => $listaCabaz->itens
                ->where('cabaz_tipo', $tipo)
                ->sortBy([['ordem', 'asc'], ['produto', 'asc']])
                ->values(),
        ]);

        return view('lista-cabazes.edit', [
            'lista' => $listaCabaz,
            'tipos' => self::TIPOS,
            'itensPorTipo' => $itensPorTipo,
            'resumo' => $this->resumoPorTipo($listaCabaz),
        ]);
    }

    /**
     * Grava a lista toda de uma vez: os quatro tipos e todas as linhas.
     * Linhas sem nome de produto sao ignoradas; as marcadas para remover saem.
     */
    public function update(Request $request, ListaCabaz $listaCabaz): RedirectResponse
    {
        $data = $request->validate([
            'descricao' => ['nullable', 'string', 'max:255'],
            'estado' => ['required', 'in:rascunho,publicada'],
            'itens' => ['array'],
            'itens.*.id' => ['nullable', 'integer'],
            'itens.*.cabaz_tipo' => ['required', 'in:'.implode(',', array_keys(self::TIPOS))],
            'itens.*.produto' => ['nullable', 'string', 'max:255'],
            'itens.*.categoria' => ['nullable', 'string', 'max:255'],
            'itens.*.quantidade' => ['nullable', 'numeric', 'min:0'],
            'itens.*.unidade' => ['nullable', 'string', 'max:20'],
            'itens.*.peso_unitario_kg' => ['nullable', 'numeric', 'min:0'],
            'itens.*.preco_unitario' => ['nullable', 'numeric', 'min:0'],
            'itens.*.remover' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($listaCabaz, $data): void {
            $listaCabaz->update([
                'descricao' => $data['descricao'] ?? null,
                'estado' => $data['estado'],
            ]);

            $manter = [];
            $ordemPorTipo = [];

            foreach ($data['itens'] ?? [] as $linha) {
                $produto = trim((string) ($linha['produto'] ?? ''));
                $remover = (bool) ($linha['remover'] ?? false);

                if ($produto === '' || $remover) {
                    continue;
                }

                $tipo = $linha['cabaz_tipo'];
                $ordemPorTipo[$tipo] = ($ordemPorTipo[$tipo] ?? 0) + 1;

                $atributos = [
                    'cabaz_tipo' => $tipo,
                    'produto' => $produto,
                    'categoria' => filled($linha['categoria'] ?? null) ? trim($linha['categoria']) : null,
                    'quantidade' => (float) ($linha['quantidade'] ?? 0),
                    'unidade' => filled($linha['unidade'] ?? null) ? trim($linha['unidade']) : 'un',
                    'peso_unitario_kg' => filled($linha['peso_unitario_kg'] ?? null) ? (float) $linha['peso_unitario_kg'] : null,
                    'preco_unitario' => filled($linha['preco_unitario'] ?? null) ? (float) $linha['preco_unitario'] : null,
                    'ordem' => $ordemPorTipo[$tipo],
                ];

                $item = filled($linha['id'] ?? null)
                    ? $listaCabaz->itens()->find($linha['id'])
                    : null;

                if ($item !== null) {
                    $item->update($atributos);
                } else {
                    $item = $listaCabaz->itens()->create($atributos);
                }

                $manter[] = $item->id;
            }

            // O que nao veio no formulario (ou foi marcado para remover) sai.
            $listaCabaz->itens()->whereNotIn('id', $manter ?: [0])->delete();
        });

        return redirect()->route('lista-cabazes.edit', $listaCabaz)->with('status', 'Lista guardada.');
    }

    public function destroy(ListaCabaz $listaCabaz): RedirectResponse
    {
        $listaCabaz->delete();

        return redirect()->route('lista-cabazes.index')->with('status', 'Lista removida.');
    }

    /** Totais por tipo: quantas linhas, quantas pecas e quanto custa. */
    private function resumoPorTipo(ListaCabaz $lista): array
    {
        return collect(self::TIPOS)->mapWithKeys(function (string $label, string $tipo) use ($lista): array {
            $itens = $lista->itens->where('cabaz_tipo', $tipo);

            $custo = $itens->sum(fn (ListaCabazItem $item): float => (float) ($item->custoUnitario() ?? 0));
            $semPreco = $itens->filter(fn (ListaCabazItem $item): bool => $item->custoUnitario() === null)->count();

            return [$tipo => [
                'linhas' => $itens->count(),
                'quantidade' => round((float) $itens->sum('quantidade'), 3),
                'custo' => round($custo, 2),
                'sem_preco' => $semPreco,
            ]];
        })->all();
    }

    /** Semana/mes/ano sugeridos para a proxima lista. */
    private function sugestaoProximaSemana(): array
    {
        $proxima = now()->addWeek();

        return [
            'semana_numero' => (int) $proxima->isoWeek(),
            'ano' => (int) $proxima->year,
            'mes' => (int) $proxima->month,
        ];
    }
}
