<?php

namespace App\Http\Controllers;

use App\Models\Corporate;
use App\Models\CorporateFatura;
use App\Services\MoloniService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Historico das faturas emitidas no Moloni a partir da app (tabela
 * corporate_faturas): quem, que ciclo, quanto e o PDF.
 */
class FaturasController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'inicio' => ['nullable', 'date'],
            'fim' => ['nullable', 'date'],
            'corporate_id' => ['nullable', 'integer'],
            'estado' => ['nullable', 'in:enviadas,por_enviar'],
        ]);

        $inicio = filled($filtros['inicio'] ?? null) ? Carbon::parse($filtros['inicio'])->startOfDay() : null;
        $fim = filled($filtros['fim'] ?? null) ? Carbon::parse($filtros['fim'])->endOfDay() : null;
        $q = trim((string) ($filtros['q'] ?? ''));
        $corporateId = (int) ($filtros['corporate_id'] ?? 0);
        $estado = (string) ($filtros['estado'] ?? '');

        $faturas = CorporateFatura::query()
            ->when($inicio, fn ($query) => $query->where('emitida_em', '>=', $inicio))
            ->when($fim, fn ($query) => $query->where('emitida_em', '<=', $fim))
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q): void {
                $sub->where('nome', 'like', "%{$q}%")
                    ->orWhere('nif', 'like', "%{$q}%")
                    ->orWhere('referencia_cliente', 'like', "%{$q}%")
                    ->orWhere('document_id', 'like', "%{$q}%");
            }))
            ->when($corporateId > 0, fn ($query) => $query->whereJsonContains('corporate_ids', $corporateId))
            ->when($estado === 'enviadas', fn ($query) => $query->whereNotNull('enviada_em'))
            ->when($estado === 'por_enviar', fn ($query) => $query->whereNull('enviada_em'))
            ->orderByDesc('emitida_em')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Nomes das empresas envolvidas, para mostrar a sucursal certa.
        $ids = collect($faturas->items())
            ->flatMap(fn (CorporateFatura $f): array => (array) ($f->corporate_ids ?? []))
            ->unique()
            ->all();

        $empresas = Corporate::query()
            ->whereIn('id', $ids)
            ->get()
            ->mapWithKeys(fn (Corporate $c): array => [$c->id => trim($c->empresa.' '.($c->sucursal ?? ''))]);

        $totais = CorporateFatura::query()
            ->when($inicio, fn ($query) => $query->where('emitida_em', '>=', $inicio))
            ->when($fim, fn ($query) => $query->where('emitida_em', '<=', $fim))
            ->when($q !== '', fn ($query) => $query->where(function ($sub) use ($q): void {
                $sub->where('nome', 'like', "%{$q}%")
                    ->orWhere('nif', 'like', "%{$q}%")
                    ->orWhere('referencia_cliente', 'like', "%{$q}%")
                    ->orWhere('document_id', 'like', "%{$q}%");
            }))
            ->when($corporateId > 0, fn ($query) => $query->whereJsonContains('corporate_ids', $corporateId))
            ->when($estado === 'enviadas', fn ($query) => $query->whereNotNull('enviada_em'))
            ->when($estado === 'por_enviar', fn ($query) => $query->whereNull('enviada_em'))
            ->selectRaw('count(*) as n, coalesce(sum(total), 0) as soma, sum(case when enviada_em is null then 1 else 0 end) as por_enviar')
            ->first();

        return view('faturas.index', [
            'faturas' => $faturas,
            'empresas' => $empresas,
            'q' => $q,
            'inicio' => $filtros['inicio'] ?? '',
            'fim' => $filtros['fim'] ?? '',
            'numero' => (int) ($totais->n ?? 0),
            'somaTotal' => (float) ($totais->soma ?? 0),
            'porEnviar' => (int) ($totais->por_enviar ?? 0),
            'estado' => $estado,
        ]);
    }

    /** Marca ou desmarca, a mao, que a fatura ja seguiu para o cliente. */
    public function enviada(Request $request, CorporateFatura $fatura): RedirectResponse
    {
        if ($fatura->enviada_em !== null) {
            $fatura->update(['enviada_em' => null, 'enviada_por' => null]);

            return back()->with('status', 'Fatura #'.$fatura->document_id.' marcada como POR enviar.');
        }

        $fatura->update([
            'enviada_em' => now(),
            'enviada_por' => optional($request->user())->name,
        ]);

        return back()->with('status', 'Fatura #'.$fatura->document_id.' marcada como enviada.');
    }

    /** Abre o PDF do documento no Moloni (o link e temporario, por isso pede-se na hora). */
    public function pdf(CorporateFatura $fatura, MoloniService $moloni): RedirectResponse
    {
        $url = $moloni->pdfUrl((int) $fatura->document_id);

        if ($url === null) {
            return back()->withErrors(['fatura' => 'Nao foi possivel obter o PDF do documento #'.$fatura->document_id.' no Moloni. Pode ter sido apagado.']);
        }

        return redirect()->away($url);
    }
}
