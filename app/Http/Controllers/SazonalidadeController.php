<?php

namespace App\Http\Controllers;

use App\Models\CabazTemplate;
use App\Models\Sazonalidade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SazonalidadeController extends Controller
{
    private const TIPOS = [
        'mini' => 'Mini',
        'pequeno' => 'Pequeno',
        'medio' => 'Médio',
        'grande' => 'Grande',
    ];

    private const CATEGORIAS = ['fruta', 'legume', 'hortalica', 'outro'];

    public function index(): View
    {
        return view('sazonalidade.index', [
            'produtos' => Sazonalidade::query()->orderBy('categoria')->orderBy('produto')->get(),
            'templates' => CabazTemplate::query()->orderBy('cabaz_tipo')->orderBy('ordem')->get()->groupBy('cabaz_tipo'),
            'categorias' => self::CATEGORIAS,
            'tipos' => self::TIPOS,
            'meses' => $this->meses(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'produto' => ['required', 'string', 'max:255', 'unique:sazonalidade,produto'],
            'categoria' => ['required', 'string', 'max:50'],
            'meses' => ['required', 'array', 'min:1'],
            'meses.*' => ['required', 'integer', 'min:1', 'max:12'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        Sazonalidade::create($data);

        return back()->with('status', "Produto \"{$data['produto']}\" adicionado.");
    }

    public function destroy(Sazonalidade $sazonalidade): RedirectResponse
    {
        $sazonalidade->delete();

        return back()->with('status', "Produto \"{$sazonalidade->produto}\" removido.");
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cabaz_tipo' => ['required', 'in:mini,pequeno,medio,grande'],
            'categoria' => ['required', 'string', 'max:50'],
            'quantidade_itens' => ['required', 'integer', 'min:1', 'max:50'],
            'quantidade_por_item' => ['required', 'numeric', 'gt:0'],
            'unidade' => ['required', 'string', 'max:20'],
            'peso_unitario_kg' => ['nullable', 'numeric', 'gt:0'],
            'ordem' => ['nullable', 'integer', 'min:0'],
        ]);

        CabazTemplate::updateOrCreate(
            ['cabaz_tipo' => $data['cabaz_tipo'], 'categoria' => $data['categoria']],
            $data
        );

        return back()->with('status', 'Regra do cabaz guardada.');
    }

    public function destroyTemplate(CabazTemplate $cabazTemplate): RedirectResponse
    {
        $cabazTemplate->delete();

        return back()->with('status', 'Regra removida.');
    }

    private function meses(): array
    {
        return [
            1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr',
            5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez',
        ];
    }
}
