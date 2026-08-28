<?php

namespace App\Http\Controllers;

use App\Models\Corporate;
use App\Models\WooOrder;
use App\Services\FaturacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class FaturacaoController extends Controller
{
    public function __construct(private readonly FaturacaoService $faturacao) {}

    /**
     * Emite a Fatura-Recibo Moloni de uma subscricao (cabaz composto).
     */
    public function subscricao(Request $request, WooOrder $encomenda): RedirectResponse
    {
        $forcar = $request->boolean('forcar');
        $metodo = $request->filled('payment_method_id') ? (int) $request->input('payment_method_id') : null;

        try {
            $resultado = $this->faturacao->emitirFaturaReciboSubscricao($encomenda, $forcar, $metodo);
        } catch (Throwable $exception) {
            return back()->withErrors(['fatura' => $exception->getMessage()]);
        }

        $mensagem = "Fatura-Recibo emitida no Moloni (#{$resultado['document_id']}).";

        if ($resultado['pdf_url'] !== null) {
            $mensagem .= ' PDF: '.$resultado['pdf_url'];
        }

        return back()->with('status', $mensagem);
    }

    /**
     * Guarda os produtos que compoem o cabaz de uma encomenda B2C (usados na fatura).
     */
    public function produtosB2c(Request $request, WooOrder $encomenda): RedirectResponse
    {
        $data = $request->validate([
            'produtos' => ['nullable', 'string', 'max:5000'],
        ]);

        $lista = collect(preg_split('/\r\n|\r|\n/', (string) ($data['produtos'] ?? '')))
            ->map(fn (string $linha): string => trim($linha))
            ->filter()
            ->values()
            ->all();

        $encomenda->update(['fatura_produtos' => $lista]);

        return back()->with('status', count($lista).' produto(s) guardado(s) para a fatura.');
    }

    /**
     * Emite a Fatura Moloni. Com NIF, emite UMA FATURA POR SUCURSAL desse
     * contribuinte (nunca agrupa); com corporate_id, emite so essa sucursal.
     */
    public function empresas(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nif' => ['nullable', 'string', 'max:20'],
            'corporate_id' => ['nullable', 'integer', 'exists:corporates,id'],
            'data_referencia' => ['nullable', 'date'],
            'referencia_cliente' => ['nullable', 'string', 'max:255'],
            'forcar' => ['nullable', 'boolean'],
        ]);

        if (blank($data['nif'] ?? null) && blank($data['corporate_id'] ?? null)) {
            return back()->withErrors(['fatura' => 'Indique o NIF ou a empresa a faturar.']);
        }

        // Data de referencia para determinar o ciclo de faturacao de 4 semanas.
        $dataRef = filled($data['data_referencia'] ?? null)
            ? \Illuminate\Support\Carbon::parse($data['data_referencia'])
            : now();
        $refCliente = filled($data['referencia_cliente'] ?? null) ? $data['referencia_cliente'] : null;
        $forcar = (bool) ($data['forcar'] ?? false);

        try {
            if (filled($data['corporate_id'] ?? null)) {
                $empresa = Corporate::findOrFail($data['corporate_id']);
                $resultado = $this->faturacao->emitirFaturaEmpresa($empresa, $dataRef, $forcar, $refCliente);
            } else {
                $resultado = $this->faturacao->emitirFaturaEmpresasPorNif($data['nif'], $dataRef, $forcar, $refCliente);
            }
        } catch (Throwable $exception) {
            return back()->withErrors(['fatura' => $exception->getMessage()]);
        }

        // Uma fatura POR SUCURSAL: o resultado pode trazer varios documentos.
        $documentos = $resultado['documentos'] ?? [$resultado];
        $erros = $resultado['erros'] ?? [];

        $mensagem = count($documentos).' fatura(s) emitida(s) no Moloni: #'
            .implode(', #', array_column($documentos, 'document_id')).'.';

        $pdfs = array_filter(array_column($documentos, 'pdf_url'));

        if ($pdfs !== []) {
            $mensagem .= ' PDF: '.implode(' | ', $pdfs);
        }

        if ($erros !== []) {
            return back()->with('status', $mensagem)->withErrors(['fatura' => 'Falhou em: '.implode(' | ', $erros)]);
        }

        return back()->with('status', $mensagem);
    }
}
