<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCorporateHistoricoRequest;
use App\Http\Requests\StoreCorporateRequest;
use App\Http\Requests\UpdateCorporateRequest;
use App\Models\Corporate;
use App\Models\CorporateHistorico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CorporateController extends Controller
{
    private const DIAS = ['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta'];

    private const FRUTAS = ['banana', 'maca', 'pera', 'laranja', 'kiwi', 'uvas', 'fruta_epoca'];

    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $dia = $request->string('dia')->toString();
        $ativo = $request->string('ativo')->toString();
        $periodicidade = $request->string('periodicidade_entrega')->toString();

        return view('corporates.index', [
            'q' => $q,
            'dia' => $dia,
            'ativo' => $ativo,
            'periodicidade' => $periodicidade,
            'dias' => self::DIAS,
            'corporates' => Corporate::query()
                ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                    $query->where('empresa', 'like', "%{$q}%")
                        ->orWhere('sucursal', 'like', "%{$q}%")
                        ->orWhere('fatura_morada', 'like', "%{$q}%")
                        ->orWhere('responsavel_nome', 'like', "%{$q}%")
                        ->orWhere('responsavel_telefone', 'like', "%{$q}%");
                }))
                ->when(in_array($dia, self::DIAS, true), fn ($query) => $query->whereJsonContains('dias_entrega', $dia))
                ->when(in_array($periodicidade, ['semanal', 'quinzenal'], true), fn ($query) => $query->where('periodicidade_entrega', $periodicidade))
                ->when($ativo === '1', fn ($query) => $query->where('ativo', true))
                ->when($ativo === '0', fn ($query) => $query->where('ativo', false))
                ->orderBy('empresa')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('corporates.create', ['corporate' => new Corporate]);
    }

    public function store(StoreCorporateRequest $request): RedirectResponse
    {
        Corporate::create($this->payload($request->validated()));

        return redirect()->route('corporates.index')->with('status', 'Empresa criada com sucesso.');
    }

    public function show(Corporate $corporate): View
    {
        $corporate->load([
            'atribuicoes.user',
            'registosEntrega.user',
            'historicos' => fn ($query) => $query->latest('data')->latest(),
            'historicos.user',
        ]);

        return view('corporates.show', compact('corporate'));
    }

    public function edit(Corporate $corporate): View
    {
        return view('corporates.edit', compact('corporate'));
    }

    public function update(UpdateCorporateRequest $request, Corporate $corporate): RedirectResponse
    {
        $corporate->update($this->payload($request->validated()));

        return redirect()->route('corporates.index')->with('status', 'Empresa atualizada com sucesso.');
    }

    public function destroy(Corporate $corporate): RedirectResponse
    {
        $corporate->delete();

        return redirect()->route('corporates.index')->with('status', 'Empresa removida.');
    }

    public function storeHistorico(StoreCorporateHistoricoRequest $request, Corporate $corporate): RedirectResponse
    {
        $corporate->historicos()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Historico adicionado.');
    }

    public function destroyHistorico(Corporate $corporate, CorporateHistorico $historico): RedirectResponse
    {
        abort_unless($historico->corporate_id === $corporate->id, 404);

        $historico->delete();

        return back()->with('status', 'Historico removido.');
    }

    private function payload(array $data): array
    {
        $frutas = collect(self::FRUTAS)
            ->mapWithKeys(fn (string $fruta) => [$fruta => $this->quantidadeFruta($data['frutas'][$fruta] ?? 0, $fruta)])
            ->all();

        $frutasPorDia = collect(self::DIAS)
            ->mapWithKeys(function (string $dia) use ($data, $frutas): array {
                $valoresDia = collect(self::FRUTAS)
                    ->mapWithKeys(fn (string $fruta) => [$fruta => $this->quantidadeFruta($data['frutas_por_dia'][$dia][$fruta] ?? 0, $fruta)])
                    ->all();

                if (in_array($dia, $data['dias_entrega'] ?? [], true) && array_sum($valoresDia) <= 0) {
                    $valoresDia = $frutas;
                }

                return [$dia => $valoresDia];
            })
            ->filter(fn (array $frutas) => array_sum($frutas) > 0)
            ->all();

        return [
            ...$data,
            'frutas' => $frutas,
            'frutas_por_dia' => $frutasPorDia,
            'ativo' => (bool) ($data['ativo'] ?? false),
            'quinzenal_referencia' => $data['periodicidade_entrega'] === 'quinzenal' ? ($data['quinzenal_referencia'] ?? null) : null,
        ];
    }

    private function quantidadeFruta(mixed $value, string $fruta): int|float
    {
        if ($fruta === 'uvas') {
            return round(max(0, (float) $value), 2);
        }

        return (int) $value;
    }
}
