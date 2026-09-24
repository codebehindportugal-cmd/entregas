<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\CabazProdutoResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Gestao da FRUTA DA EPOCA: que fruta(s) concreta(s) se estao a entregar.
 *
 * Pode definir-se por MES (YYYY-MM) ou por SEMANA (YYYY-Www, manda sobre o mes)
 * e pode haver varias frutas ao mesmo tempo ("Ameixa, Uva"). O nome aparece nas
 * guias (fruta da semana da entrega) e nas faturas (juncao das semanas do ciclo),
 * na linha-filha do artigo composto: "Fruta da epoca 250g (...) — Ameixa e Uva".
 * Fica no Setting faturacao_mapa_produtos, com um bloco "default" e blocos por periodo.
 */
class FrutaEpocaController extends Controller
{
    private const PERIODO_REGEX = '/^(default|\d{4}-\d{2}|\d{4}-W\d{2})$/';

    public function index(): View
    {
        $mapa = $this->mapa();

        $periodos = collect($mapa)
            ->filter(fn ($bloco, $chave): bool => is_array($bloco) && isset($bloco['fruta_epoca']))
            ->map(fn (array $bloco, string $chave): array => [
                'periodo' => $chave,
                'tipo' => $this->tipo($chave),
                'label' => $this->label($chave),
                'nome' => (string) ($bloco['fruta_epoca']['nome'] ?? ''),
                'referencia' => (string) ($bloco['fruta_epoca']['referencia'] ?? ''),
                'ordem' => $this->ordem($chave),
            ])
            ->sortByDesc('ordem')
            ->values();

        $hoje = now()->startOfDay();
        $mesAtual = $hoje->format('Y-m');
        $semanaAtual = CabazProdutoResolver::chaveSemana($hoje);
        $frutas = (new CabazProdutoResolver)->frutasEpoca($hoje->toDateString());

        return view('fruta-epoca.index', [
            'periodos' => $periodos,
            'mesAtual' => $mesAtual,
            'semanaAtual' => $semanaAtual,
            'labelSemanaAtual' => $this->label($semanaAtual),
            'atual' => $frutas !== [] ? implode(', ', $frutas) : 'Fruta da epoca',
            'origemAtual' => isset($mapa[$semanaAtual]['fruta_epoca']['nome'])
                ? 'semana'
                : (isset($mapa[$mesAtual]['fruta_epoca']['nome']) ? 'mes' : null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tipo' => ['nullable', 'in:mes,semana'],
            'periodo' => ['nullable', 'string', 'regex:/^(default|\d{4}-\d{2})$/'],
            'semana' => ['nullable', 'string', 'regex:/^\d{4}-W\d{2}$/'],
            'nome' => ['required', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
        ], [
            'periodo.regex' => 'O mes tem de ser AAAA-MM.',
            'semana.regex' => 'A semana tem de ser AAAA-Wnn.',
        ]);

        $periodo = ($data['tipo'] ?? 'mes') === 'semana' ? ($data['semana'] ?? null) : ($data['periodo'] ?? null);

        if (blank($periodo)) {
            return back()->withInput()->withErrors(['periodo' => ($data['tipo'] ?? 'mes') === 'semana' ? 'Escolhe a semana.' : 'Escolhe o mes.']);
        }

        // Normaliza a lista: "ameixa ,uva" -> "Ameixa, Uva".
        $nomes = array_values(array_filter(array_map(
            fn (string $n): string => mb_convert_case(trim($n), MB_CASE_TITLE, 'UTF-8'),
            preg_split('/\s*[,;\/+]\s*|\s+e\s+/u', trim($data['nome'])) ?: [],
        ), fn (string $n): bool => $n !== ''));
        $nome = implode(', ', $nomes);

        $mapa = $this->mapa();
        $mapa[$periodo]['fruta_epoca']['nome'] = $nome;

        if (filled($data['referencia'] ?? null)) {
            $mapa[$periodo]['fruta_epoca']['referencia'] = trim($data['referencia']);
        } else {
            unset($mapa[$periodo]['fruta_epoca']['referencia']);
        }

        $this->guardar($mapa);

        return redirect()->route('fruta-epoca.index')
            ->with('status', 'Fruta da epoca de '.$this->label($periodo).': '.$nome.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'periodo' => ['required', 'string', 'regex:'.self::PERIODO_REGEX],
        ]);

        $mapa = $this->mapa();
        unset($mapa[$data['periodo']]['fruta_epoca']);

        if (($mapa[$data['periodo']] ?? []) === []) {
            unset($mapa[$data['periodo']]);
        }

        $this->guardar($mapa);

        return redirect()->route('fruta-epoca.index')
            ->with('status', 'Removida a fruta da epoca de '.$this->label($data['periodo']).'.');
    }

    private function mapa(): array
    {
        $raw = Setting::query()->where('key', CabazProdutoResolver::SETTING_KEY)->value('value');
        $mapa = filled($raw) ? json_decode((string) $raw, true) : [];

        return is_array($mapa) ? $mapa : [];
    }

    private function guardar(array $mapa): void
    {
        Setting::query()->updateOrCreate(
            ['key' => CabazProdutoResolver::SETTING_KEY],
            ['value' => json_encode($mapa, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );
    }

    /** Nomes dos meses em PT (o APP_LOCALE do projeto e "en"). */
    private const MESES = [
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Marco', 4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    ];

    private function tipo(string $periodo): string
    {
        return match (true) {
            $periodo === 'default' => 'defeito',
            (bool) preg_match('/^\d{4}-W\d{2}$/', $periodo) => 'semana',
            default => 'mes',
        };
    }

    /** Segunda-feira da semana ISO "2026-W39". */
    private function segundaDaSemana(string $semana): ?Carbon
    {
        if (! preg_match('/^(\d{4})-W(\d{2})$/', $semana, $m)) {
            return null;
        }

        return now()->setISODate((int) $m[1], (int) $m[2])->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    /** Chave para ordenar a tabela (mais recente primeiro). */
    private function ordem(string $periodo): string
    {
        if ($periodo === 'default') {
            return '0000-00-00';
        }

        if (($segunda = $this->segundaDaSemana($periodo)) !== null) {
            return $segunda->format('Y-m-d').'b';
        }

        return $periodo.'-00';
    }

    private function label(string $periodo): string
    {
        if ($periodo === 'default') {
            return 'Todos os meses (por defeito)';
        }

        if (($segunda = $this->segundaDaSemana($periodo)) !== null) {
            return 'Semana de '.$segunda->format('d/m').' a '.$segunda->copy()->addDays(6)->format('d/m/Y');
        }

        if (! preg_match('/^(\d{4})-(\d{2})$/', $periodo, $m)) {
            return $periodo;
        }

        return (self::MESES[(int) $m[2]] ?? $periodo).' de '.$m[1];
    }
}
