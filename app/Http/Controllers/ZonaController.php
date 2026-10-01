<?php

namespace App\Http\Controllers;

use App\Models\AtribuicaoEntrega;
use App\Models\User;
use App\Models\Zona;
use App\Models\ZonaHorario;
use App\Models\ZonaSubstituicao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Zonas de entrega: quem faz cada zona em cada dia da semana, substituicoes
 * por datas (ferias, faltas) e a conversao das atribuicoes antigas, que eram
 * por colaborador.
 */
class ZonaController extends Controller
{
    public function index(): View
    {
        $zonas = Zona::with(['horarios', 'substituicoes' => fn ($query) => $query->whereDate('fim', '>=', now()->toDateString())->orderBy('inicio'), 'substituicoes.user'])
            ->withCount('atribuicoes')
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();

        // Atribuicoes de antes das zonas, agrupadas pelo colaborador que as tinha.
        $porConverter = AtribuicaoEntrega::with('user')
            ->whereNull('zona_id')
            ->whereNotNull('user_id')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($atribuicoes) => [
                'user' => $atribuicoes->first()->user,
                'total' => $atribuicoes->count(),
                'dias' => $atribuicoes->pluck('dia_semana')->unique()->sortBy(fn ($dia) => array_search($dia, Zona::DIAS, true))->values(),
            ])
            ->filter(fn (array $linha): bool => $linha['user'] !== null)
            ->sortBy(fn (array $linha): string => $linha['user']->name)
            ->values();

        return view('zonas.index', [
            'zonas' => $zonas,
            'dias' => Zona::DIAS,
            'colaboradores' => User::where('ativo', true)->orderBy('name')->get(),
            'porConverter' => $porConverter,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:80', Rule::unique('zonas', 'nome')],
            'cor' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        Zona::create([
            'nome' => $data['nome'],
            'cor' => $data['cor'] ?? '#64748B',
            'ordem' => (int) Zona::max('ordem') + 1,
            'ativo' => true,
        ]);

        return back()->with('status', 'Zona criada.');
    }

    public function update(Request $request, Zona $zona): RedirectResponse
    {
        $data = $request->validate([
            'nome' => ['required', 'string', 'max:80', Rule::unique('zonas', 'nome')->ignore($zona->id)],
            'cor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:999'],
            'ativo' => ['nullable', 'boolean'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'codigos_postais' => ['nullable', 'string', 'max:255', 'regex:/^\s*(\d{4}(-\d{4})?)?([\s,;]+\d{4}(-\d{4})?)*\s*$/'],
            'partida_cp' => ['nullable', 'string', 'max:20', 'regex:/^\s*\d{4}(-\d{3})?\s*$/'],
        ], [
            'partida_cp.regex' => 'A partida da volta e um codigo postal: 0000 ou 0000-000.',
            'codigos_postais.regex' => 'Escreva os códigos postais assim: 2300-2599, 3000-3299 (só os 4 primeiros dígitos).',
        ]);

        $zona->update([
            'nome' => $data['nome'],
            'cor' => $data['cor'],
            'ordem' => $data['ordem'] ?? $zona->ordem,
            'ativo' => $request->boolean('ativo'),
            'descricao' => $data['descricao'] ?? null,
            'codigos_postais' => $data['codigos_postais'] ?? null,
            'partida_cp' => filled($data['partida_cp'] ?? null) ? trim($data['partida_cp']) : null,
        ]);

        return back()->with('status', "Zona {$zona->nome} guardada.");
    }

    /**
     * Quem faz cada zona em cada dia da semana (a grelha toda de uma vez).
     * Cada celula e um colaborador ("u:5") ou outra zona ("z:3"), quando
     * nesse dia a zona vai com quem faz essa.
     */
    public function updateHorario(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'horario' => ['array'],
            'horario.*' => ['array'],
            'horario.*.*' => ['nullable', 'regex:/^[uz]:\d+$/'],
        ]);

        $zonaIds = Zona::pluck('id')->all();
        $userIds = User::pluck('id')->all();

        DB::transaction(function () use ($data, $zonaIds, $userIds): void {
            foreach ($data['horario'] ?? [] as $zonaId => $dias) {
                $zonaId = (int) $zonaId;

                if (! in_array($zonaId, $zonaIds, true)) {
                    continue;
                }

                foreach ($dias as $dia => $valor) {
                    if (! in_array($dia, Zona::DIAS, true)) {
                        continue;
                    }

                    [$tipo, $id] = filled($valor) ? explode(':', $valor) : [null, null];
                    $id = (int) $id;
                    $valido = ($tipo === 'u' && in_array($id, $userIds, true))
                        || ($tipo === 'z' && $id !== $zonaId && in_array($id, $zonaIds, true));

                    if (! $valido) {
                        ZonaHorario::where('zona_id', $zonaId)->where('dia_semana', $dia)->delete();

                        continue;
                    }

                    ZonaHorario::updateOrCreate(
                        ['zona_id' => $zonaId, 'dia_semana' => $dia],
                        ['user_id' => $tipo === 'u' ? $id : null, 'acompanha_zona_id' => $tipo === 'z' ? $id : null],
                    );
                }
            }
        });

        return back()->with('status', 'Horário das zonas guardado.');
    }

    public function storeSubstituicao(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'zona_id' => ['required', 'integer', 'exists:zonas,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'inicio' => ['required', 'date'],
            'fim' => ['required', 'date', 'after_or_equal:inicio'],
            'nota' => ['nullable', 'string', 'max:120'],
        ], [
            'fim.after_or_equal' => 'A data de fim tem de ser igual ou depois do início.',
        ]);

        $sobreposta = ZonaSubstituicao::where('zona_id', $data['zona_id'])
            ->whereDate('inicio', '<=', $data['fim'])
            ->whereDate('fim', '>=', $data['inicio'])
            ->exists();

        if ($sobreposta) {
            return back()->withInput()->withErrors(['inicio' => 'Já há uma substituição nesta zona nessas datas. Apague-a primeiro.']);
        }

        ZonaSubstituicao::create($data);

        return back()->with('status', 'Substituição guardada.');
    }

    public function destroySubstituicao(ZonaSubstituicao $substituicao): RedirectResponse
    {
        $substituicao->delete();

        return back()->with('status', 'Substituição apagada.');
    }

    /**
     * Passa as atribuicoes antigas de cada colaborador para a zona escolhida
     * e, nos dias em que ele as fazia, poe-no a fazer a zona (se a zona ainda
     * nao tiver ninguem nesse dia).
     */
    public function converter(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'zona' => ['array'],
            'zona.*' => ['nullable', 'integer', 'exists:zonas,id'],
        ]);

        $convertidas = 0;

        DB::transaction(function () use ($data, &$convertidas): void {
            foreach ($data['zona'] ?? [] as $userId => $zonaId) {
                if (blank($zonaId)) {
                    continue;
                }

                $atribuicoes = AtribuicaoEntrega::whereNull('zona_id')->where('user_id', (int) $userId)->get();

                foreach ($atribuicoes->pluck('dia_semana')->unique() as $dia) {
                    ZonaHorario::firstOrCreate(
                        ['zona_id' => (int) $zonaId, 'dia_semana' => $dia],
                        ['user_id' => (int) $userId],
                    );
                }

                $convertidas += AtribuicaoEntrega::whereIn('id', $atribuicoes->pluck('id'))->update(['zona_id' => (int) $zonaId]);
            }
        });

        return back()->with('status', $convertidas === 1 ? '1 entrega passou para zona.' : "{$convertidas} entregas passaram para zonas.");
    }
}
