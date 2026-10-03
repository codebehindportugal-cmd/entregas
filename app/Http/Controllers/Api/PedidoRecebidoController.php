<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PedidoRecebido;
use App\Services\PedidosRecebidosService;
use App\Support\PedidosSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Caixa de pedidos, do lado da API.
 *
 * Ao fim do dia o Claude le os emails com a etiqueta de encomendas, interpreta
 * cada um no formato do /encomendas/validar e deixa-os aqui (POST /lote). Os que
 * chegaram pelo webhook do WhatsApp ja estao na caixa como "novo": le-os com
 * GET ?estado=novo e devolve a interpretacao com PUT /{id}/interpretacao.
 *
 * Nada daqui cria encomendas: isso so acontece no backoffice, com o Confirmar.
 */
class PedidoRecebidoController extends Controller
{
    use RespondeJson;

    public function definicoes(): JsonResponse
    {
        return $this->ok(PedidosSettings::publicas());
    }

    public function index(Request $request): JsonResponse
    {
        $pedidos = PedidoRecebido::query()
            ->when($request->filled('estado'), fn ($q) => $q->whereIn('estado', explode(',', (string) $request->string('estado'))))
            ->when($request->filled('canal'), fn ($q) => $q->where('canal', $request->string('canal')))
            ->when($request->filled('origem_id'), fn ($q) => $q->where('origem_id', $request->string('origem_id')))
            ->latest('id')
            ->limit(min(200, max(1, (int) $request->integer('limite', 100))))
            ->get();

        return $this->ok([
            'total' => $pedidos->count(),
            'pedidos' => $pedidos->map(fn (PedidoRecebido $p): array => $this->formatar($p))->values()->all(),
        ]);
    }

    public function store(Request $request, PedidosRecebidosService $service): JsonResponse
    {
        $dados = $this->validarPedido($request->all());

        if ($dados instanceof JsonResponse) {
            return $dados;
        }

        $resultado = $service->registar($dados);

        if (! $resultado['repetido']) {
            $service->avisar(1);
        }

        return $this->ok(
            $this->formatar($resultado['pedido'], $resultado['repetido']),
            $this->avisosRepetido($resultado),
            $resultado['repetido'] ? 200 : 201,
        );
    }

    /** Varios pedidos de uma vez (o trabalho do fim do dia). Um so aviso no ntfy. */
    public function lote(Request $request, PedidosRecebidosService $service): JsonResponse
    {
        $lista = $request->input('pedidos');

        if (! is_array($lista) || $lista === []) {
            return $this->erro422([['codigo' => 'SEM_PEDIDOS', 'linha' => null, 'mensagem' => 'Manda {"pedidos": [...]} com pelo menos um pedido.', 'sugestoes' => []]]);
        }

        $resultados = [];
        $novos = 0;

        foreach (array_values($lista) as $indice => $item) {
            $dados = $this->validarPedido(is_array($item) ? $item : []);

            if ($dados instanceof JsonResponse) {
                $resultados[] = ['indice' => $indice, 'sucesso' => false, 'erros' => $dados->getData(true)['erros']];

                continue;
            }

            $resultado = $service->registar($dados);
            $novos += $resultado['repetido'] ? 0 : 1;
            $resultados[] = ['indice' => $indice, 'sucesso' => true] + $this->formatar($resultado['pedido'], $resultado['repetido']);
        }

        $service->avisar($novos);

        return $this->ok([
            'novos' => $novos,
            'resultados' => $resultados,
        ]);
    }

    public function interpretacao(Request $request, PedidoRecebido $pedidoRecebido, PedidosRecebidosService $service): JsonResponse
    {
        $validador = Validator::make($request->all(), [
            'pedido' => ['nullable', 'array'],
            'duvidas' => ['nullable', 'array'],
            'duvidas.*' => ['string', 'max:1000'],
        ]);

        if ($validador->fails()) {
            return $this->erro422($this->errosDoValidador($validador->errors()->toArray()));
        }

        if (! $pedidoRecebido->aberto()) {
            return $this->erro422([['codigo' => 'PEDIDO_FECHADO', 'linha' => null, 'mensagem' => 'Este pedido ja esta '.mb_strtolower($pedidoRecebido->etiquetaEstado()).'.', 'sugestoes' => []]]);
        }

        $service->interpretar($pedidoRecebido, $request->input('pedido'), (array) $request->input('duvidas', []));

        return $this->ok($this->formatar($pedidoRecebido->refresh()));
    }

    /** @return array<string,mixed>|JsonResponse */
    private function validarPedido(array $dados): array|JsonResponse
    {
        $validador = Validator::make($dados, [
            'canal' => ['required', Rule::in(PedidoRecebido::CANAIS)],
            'origem_id' => ['nullable', 'string', 'max:191', 'required_if:canal,email'],
            'remetente' => ['nullable', 'string', 'max:255'],
            'assunto' => ['nullable', 'string', 'max:255'],
            'recebido_em' => ['nullable', 'date'],
            'texto_original' => ['required', 'string', 'max:50000'],
            'pedido' => ['nullable', 'array'],
            'duvidas' => ['nullable', 'array'],
            'duvidas.*' => ['string', 'max:1000'],
        ], [
            'origem_id.required_if' => 'Num email manda o id da mensagem do Gmail, para nao entrar duas vezes.',
        ]);

        if ($validador->fails()) {
            return $this->erro422($this->errosDoValidador($validador->errors()->toArray()));
        }

        $validados = $validador->validated();

        // Distinguir "nao mandou pedido" de "mandou pedido: null".
        if (array_key_exists('pedido', $dados)) {
            $validados['pedido'] = $dados['pedido'];
        }

        return $validados;
    }

    private function errosDoValidador(array $erros): array
    {
        return collect($erros)->map(fn (array $mensagens, string $campo): array => [
            'codigo' => 'CAMPO_INVALIDO',
            'linha' => null,
            'mensagem' => "{$campo}: ".implode(' ', $mensagens),
            'sugestoes' => [],
        ])->values()->all();
    }

    private function avisosRepetido(array $resultado): array
    {
        return $resultado['repetido']
            ? [['codigo' => 'PEDIDO_REPETIDO', 'mensagem' => 'Esta mensagem ja estava na caixa; nao foi criada outra.']]
            : [];
    }

    private function formatar(PedidoRecebido $p, bool $repetido = false): array
    {
        return [
            'id' => $p->id,
            'canal' => $p->canal,
            'origem_id' => $p->origem_id,
            'remetente' => $p->remetente,
            'assunto' => $p->assunto,
            'recebido_em' => $p->recebido_em?->toIso8601String(),
            'texto_original' => $p->texto_original,
            'estado' => $p->estado,
            'pedido' => $p->pedido,
            'duvidas' => $p->duvidas ?? [],
            'resumo' => $p->resumo,
            'avisos' => $p->avisos ?? [],
            'erros' => $p->erros ?? [],
            'encomenda_id' => $p->woo_order_id,
            'url_backoffice' => route('pedidos-recebidos.show', $p),
            'repetido' => $repetido,
        ];
    }
}
