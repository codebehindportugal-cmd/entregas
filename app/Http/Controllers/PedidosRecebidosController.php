<?php

namespace App\Http\Controllers;

use App\Models\PedidoRecebido;
use App\Services\PedidosRecebidosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/**
 * Caixa de pedidos no backoffice: ver o que chegou por email/WhatsApp, corrigir
 * o que o Claude interpretou e confirmar (so ai vai para o WooCommerce).
 */
class PedidosRecebidosController extends Controller
{
    public function index(Request $request): View
    {
        $filtro = $request->string('estado')->toString() ?: 'abertos';

        $pedidos = PedidoRecebido::query()
            ->with('wooOrder')
            ->when($filtro === 'abertos', fn ($q) => $q->whereIn('estado', PedidoRecebido::ABERTOS))
            ->when($filtro !== 'abertos' && $filtro !== 'todos', fn ($q) => $q->where('estado', $filtro))
            ->orderByRaw("case estado when 'pronto' then 0 when 'com_duvidas' then 1 when 'falta_telefone' then 2 when 'novo' then 3 else 4 end")
            ->latest('recebido_em')
            ->paginate(50)
            ->withQueryString();

        $contagens = PedidoRecebido::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('pedidos-recebidos.index', compact('pedidos', 'contagens', 'filtro'));
    }

    public function show(PedidoRecebido $pedidoRecebido): View
    {
        return view('pedidos-recebidos.show', [
            'pedido' => $pedidoRecebido->load('wooOrder', 'tratadoPor'),
            'pedidoJson' => json_encode($pedidoRecebido->pedido ?? $this->esqueleto($pedidoRecebido), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /** Correcao a mao: o JSON do pedido inteiro, ou so o telefone. */
    public function update(Request $request, PedidoRecebido $pedidoRecebido, PedidosRecebidosService $service): RedirectResponse
    {
        abort_unless($pedidoRecebido->aberto(), 422, 'Este pedido ja esta fechado.');

        $data = $request->validate([
            'pedido_json' => ['nullable', 'string', 'max:50000'],
            'telefone' => ['nullable', 'string', 'max:50'],
            'limpar_duvidas' => ['nullable', 'boolean'],
        ]);

        $pedido = $pedidoRecebido->pedido;

        if (filled($data['pedido_json'] ?? null)) {
            $pedido = json_decode($data['pedido_json'], true);

            if (! is_array($pedido)) {
                return back()->withInput()->withErrors(['pedido_json' => 'O JSON do pedido nao e valido: '.json_last_error_msg()]);
            }
        }

        if (filled($data['telefone'] ?? null)) {
            $pedido = is_array($pedido) ? $pedido : $this->esqueleto($pedidoRecebido);
            $pedido['cliente']['telefone'] = trim($data['telefone']);
        }

        $duvidas = $request->boolean('limpar_duvidas') ? [] : ($pedidoRecebido->duvidas ?? []);

        $service->interpretar($pedidoRecebido, $pedido, $duvidas);

        return redirect()->route('pedidos-recebidos.show', $pedidoRecebido)
            ->with('status', 'Pedido validado outra vez: '.mb_strtolower($pedidoRecebido->refresh()->etiquetaEstado()).'.');
    }

    public function confirmar(Request $request, PedidoRecebido $pedidoRecebido, PedidosRecebidosService $service): RedirectResponse
    {
        try {
            $order = $service->confirmar($pedidoRecebido, $request->user());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['pedido' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors(['pedido' => 'O WooCommerce recusou a encomenda: '.$exception->getMessage().' Confirma no site se nao ficou uma encomenda pendente antes de repetir.']);
        }

        return redirect()->route('pedidos-recebidos.show', $pedidoRecebido)
            ->with('status', "Encomenda #{$order->woo_id} criada no WooCommerce, a espera de pagamento.");
    }

    public function descartar(Request $request, PedidoRecebido $pedidoRecebido, PedidosRecebidosService $service): RedirectResponse
    {
        $data = $request->validate(['motivo' => ['nullable', 'string', 'max:500']]);

        $service->descartar($pedidoRecebido, $request->user(), $data['motivo'] ?? null);

        return redirect()->route('pedidos-recebidos.index')->with('status', 'Pedido descartado.');
    }

    public function reabrir(PedidoRecebido $pedidoRecebido, PedidosRecebidosService $service): RedirectResponse
    {
        $service->reabrir($pedidoRecebido);

        return redirect()->route('pedidos-recebidos.show', $pedidoRecebido)->with('status', 'Pedido reaberto.');
    }

    private function esqueleto(PedidoRecebido $pedido): array
    {
        return [
            'cliente' => ['telefone' => null],
            'perfil_woo_order_id' => null,
            'dia_entrega' => null,
            'data_entrega' => null,
            'notas' => null,
            'cupoes' => [],
            'linhas' => [
                ['texto' => '', 'quantidade' => 1, 'unidade' => null],
            ],
        ];
    }
}
