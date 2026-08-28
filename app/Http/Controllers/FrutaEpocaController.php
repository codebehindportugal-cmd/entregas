<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\CabazProdutoResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Gestao da FRUTA DA EPOCA: qual e o fruto concreto de cada mes.
 *
 * O nome guardado aqui e o que aparece nas guias de transporte e nas faturas
 * Moloni (designacao do cabaz e nome da linha-filha do artigo composto), em vez
 * do generico "Fruta da epoca". Fica no Setting faturacao_mapa_produtos, com um
 * bloco "default" e blocos por periodo (YYYY-MM).
 */
class FrutaEpocaController extends Controller
{
    public function index(): View
    {
        $mapa = $this->mapa();

        $periodos = collect($mapa)
            ->filter(fn ($bloco, $chave): bool => is_array($bloco) && isset($bloco['fruta_epoca']))
            ->map(fn (array $bloco, string $chave): array => [
                'periodo' => $chave,
                'label' => $this->label($chave),
                'nome' => (string) ($bloco['fruta_epoca']['nome'] ?? ''),
                'referencia' => (string) ($bloco['fruta_epoca']['referencia'] ?? ''),
            ])
            ->sortByDesc(fn (array $linha): string => $linha['periodo'] === 'default' ? '0000-00' : $linha['periodo'])
            ->values();

        $mesAtual = now()->format('Y-m');
        $resolver = new CabazProdutoResolver;

        return view('fruta-epoca.index', [
            'periodos' => $periodos,
            'mesAtual' => $mesAtual,
            'labelMesAtual' => $this->label($mesAtual),
            'atual' => $resolver->resolver('fruta_epoca', $mesAtual)['nome'],
            'temOverrideAtual' => isset($mapa[$mesAtual]['fruta_epoca']['nome']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'periodo' => ['required', 'string', 'regex:/^(default|\d{4}-\d{2})$/'],
            'nome' => ['required', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
        ], [
            'periodo.regex' => 'O periodo tem de ser um mes (AAAA-MM) ou "default".',
        ]);

        $mapa = $this->mapa();
        $mapa[$data['periodo']]['fruta_epoca']['nome'] = trim($data['nome']);

        if (filled($data['referencia'] ?? null)) {
            $mapa[$data['periodo']]['fruta_epoca']['referencia'] = trim($data['referencia']);
        } else {
            unset($mapa[$data['periodo']]['fruta_epoca']['referencia']);
        }

        $this->guardar($mapa);

        return redirect()->route('fruta-epoca.index')
            ->with('status', 'Fruta da epoca de '.$this->label($data['periodo']).' definida como: '.trim($data['nome']).'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'periodo' => ['required', 'string', 'regex:/^(default|\d{4}-\d{2})$/'],
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

    private function label(string $periodo): string
    {
        if ($periodo === 'default') {
            return 'Todos os meses (por defeito)';
        }

        if (! preg_match('/^(\d{4})-(\d{2})$/', $periodo, $m)) {
            return $periodo;
        }

        $mes = (int) $m[2];

        return (self::MESES[$mes] ?? $periodo).' de '.$m[1];
    }
}
