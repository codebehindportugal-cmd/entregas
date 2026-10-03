<?php

namespace App\Services;

use App\Http\Requests\Api\ValidarEncomendaApiRequest;
use App\Models\PedidoRecebido;
use App\Models\User;
use App\Models\WooOrder;
use App\Support\Ntfy;
use App\Support\PedidosSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * A caixa de pedidos: o que chega por email ou WhatsApp fica aqui guardado ate
 * alguem confirmar. Reaproveita o mesmo validador das encomendas do chat, por
 * isso as regras (unidade vs peso, cliente pelo telefone, cupoes) sao as mesmas.
 *
 * Nada vai para o WooCommerce sem uma pessoa carregar em Confirmar.
 */
class PedidosRecebidosService
{
    public function __construct(private readonly EncomendaChatService $encomendas) {}

    /**
     * Regista um pedido. Se ja existir um com o mesmo canal + origem_id devolve
     * esse (sem duplicar); se ainda estiver aberto, atualiza a interpretacao.
     *
     * @return array{pedido: PedidoRecebido, repetido: bool}
     */
    public function registar(array $dados): array
    {
        $existente = filled($dados['origem_id'] ?? null)
            ? PedidoRecebido::where('canal', $dados['canal'])->where('origem_id', $dados['origem_id'])->first()
            : null;

        if ($existente !== null) {
            if ($existente->aberto() && array_key_exists('pedido', $dados)) {
                $this->interpretar($existente, $dados['pedido'] ?? null, $dados['duvidas'] ?? []);
            }

            return ['pedido' => $existente->refresh(), 'repetido' => true];
        }

        $pedido = PedidoRecebido::create([
            'canal' => $dados['canal'],
            'origem_id' => $dados['origem_id'] ?? null,
            'remetente' => $dados['remetente'] ?? null,
            'assunto' => $dados['assunto'] ?? null,
            'recebido_em' => $dados['recebido_em'] ?? now(),
            'texto_original' => $dados['texto_original'],
            'estado' => 'novo',
        ]);

        if (array_key_exists('pedido', $dados) || filled($dados['duvidas'] ?? null)) {
            $this->interpretar($pedido, $dados['pedido'] ?? null, $dados['duvidas'] ?? []);
        }

        return ['pedido' => $pedido->refresh(), 'repetido' => false];
    }

    /** Guarda a interpretacao (feita pelo Claude ou corrigida a mao) e volta a validar. */
    public function interpretar(PedidoRecebido $pedidoRecebido, ?array $pedido, array $duvidas = []): PedidoRecebido
    {
        $pedidoRecebido->forceFill([
            'pedido' => $pedido,
            'duvidas' => array_values(array_filter(array_map('trim', array_map('strval', $duvidas)))),
        ])->save();

        return $this->avaliar($pedidoRecebido);
    }

    /** Corre o validador das encomendas e decide o estado. Nao grava no WooCommerce. */
    public function avaliar(PedidoRecebido $pedidoRecebido): PedidoRecebido
    {
        if (! $pedidoRecebido->aberto()) {
            return $pedidoRecebido;
        }

        $pedido = $pedidoRecebido->pedido;
        $duvidas = $pedidoRecebido->duvidas ?? [];

        if (! is_array($pedido) || $pedido === []) {
            $pedidoRecebido->forceFill([
                'resumo' => null,
                'avisos' => [],
                'erros' => [],
                'estado' => $duvidas !== [] ? 'com_duvidas' : 'novo',
            ])->save();

            return $pedidoRecebido;
        }

        if (blank($pedido['cliente']['telefone'] ?? null) && blank($pedido['perfil_woo_order_id'] ?? null)) {
            $pedidoRecebido->forceFill([
                'resumo' => null,
                'avisos' => [],
                'erros' => [[
                    'codigo' => 'TELEFONE_EM_FALTA',
                    'linha' => null,
                    'mensagem' => 'O pedido nao traz telefone e nao deu para identificar o cliente. Poe o telefone e volta a validar.',
                    'sugestoes' => [],
                ]],
                'estado' => 'falta_telefone',
            ])->save();

            return $pedidoRecebido;
        }

        $formato = Validator::make($pedido, (new ValidarEncomendaApiRequest)->rules());

        if ($formato->fails()) {
            $pedidoRecebido->forceFill([
                'resumo' => null,
                'avisos' => [],
                'erros' => collect($formato->errors()->toArray())
                    ->map(fn (array $mensagens, string $campo): array => [
                        'codigo' => 'FORMATO_INVALIDO',
                        'linha' => null,
                        'mensagem' => "{$campo}: ".implode(' ', $mensagens),
                        'sugestoes' => [],
                    ])->values()->all(),
                'estado' => 'com_duvidas',
            ])->save();

            return $pedidoRecebido;
        }

        $resultado = $this->encomendas->validar($pedido, gerarToken: false);

        $pedidoRecebido->forceFill([
            'resumo' => $resultado['dados'],
            'avisos' => $resultado['avisos'],
            'erros' => $resultado['erros'],
            'estado' => $resultado['erros'] !== [] || $duvidas !== [] ? 'com_duvidas' : 'pronto',
        ])->save();

        return $pedidoRecebido;
    }

    /**
     * Cria a encomenda no WooCommerce. Volta a validar antes (o preco e o stock
     * podem ter mudado desde que o pedido foi preparado).
     *
     * @throws RuntimeException quando ainda ha erros
     */
    public function confirmar(PedidoRecebido $pedidoRecebido, ?User $user = null): WooOrder
    {
        return DB::transaction(function () use ($pedidoRecebido, $user): WooOrder {
            /** @var PedidoRecebido $pedidoRecebido */
            $pedidoRecebido = PedidoRecebido::query()->lockForUpdate()->findOrFail($pedidoRecebido->id);

            // Ja criada (duplo clique, ou falhou a seguir a criar): liga e sai.
            $jaExiste = WooOrder::where('referencia_externa', $pedidoRecebido->referenciaExterna())->first();

            if ($jaExiste !== null) {
                $this->marcarCriado($pedidoRecebido, $jaExiste, $user);

                return $jaExiste;
            }

            if (! $pedidoRecebido->aberto()) {
                throw new RuntimeException('Este pedido ja foi '.mb_strtolower($pedidoRecebido->etiquetaEstado()).'.');
            }

            $this->avaliar($pedidoRecebido);

            if (($pedidoRecebido->erros ?? []) !== [] || ! is_array($pedidoRecebido->resumo)) {
                throw new RuntimeException('O pedido ainda tem erros. Corrige-o antes de confirmar.');
            }

            $resultado = $this->encomendas->criar($pedidoRecebido->resumo, $pedidoRecebido->referenciaExterna());

            $this->marcarCriado($pedidoRecebido, $resultado['order'], $user);

            return $resultado['order'];
        });
    }

    public function descartar(PedidoRecebido $pedidoRecebido, ?User $user = null, ?string $motivo = null): void
    {
        $pedidoRecebido->forceFill([
            'estado' => 'descartado',
            'tratado_por' => $user?->id,
            'tratado_em' => now(),
            'notas' => $motivo ?: $pedidoRecebido->notas,
        ])->save();
    }

    public function reabrir(PedidoRecebido $pedidoRecebido): PedidoRecebido
    {
        if ($pedidoRecebido->estado !== 'descartado') {
            return $pedidoRecebido;
        }

        $pedidoRecebido->forceFill(['estado' => 'novo', 'tratado_por' => null, 'tratado_em' => null])->save();

        return $this->avaliar($pedidoRecebido);
    }

    /** Um aviso no telemovel com quantos pedidos entraram e quantos tem duvidas. */
    public function avisar(int $novos): void
    {
        if ($novos < 1 || ! PedidosSettings::ligado('avisar_ntfy')) {
            return;
        }

        $abertos = PedidoRecebido::whereIn('estado', PedidoRecebido::ABERTOS)->get(['estado']);
        $prontos = $abertos->where('estado', 'pronto')->count();
        $porVer = $abertos->count() - $prontos;

        Ntfy::enviar(
            'pedidos',
            $novos === 1 ? '1 pedido novo na caixa' : "{$novos} pedidos novos na caixa",
            "{$prontos} prontos a confirmar, {$porVer} com duvidas ou por interpretar.",
            tags: 'inbox_tray',
            link: route('pedidos-recebidos.index'),
        );
    }

    private function marcarCriado(PedidoRecebido $pedidoRecebido, WooOrder $order, ?User $user): void
    {
        $pedidoRecebido->forceFill([
            'estado' => 'criado',
            'woo_order_id' => $order->id,
            'tratado_por' => $user?->id ?? $pedidoRecebido->tratado_por,
            'tratado_em' => $pedidoRecebido->tratado_em ?? now(),
        ])->save();
    }
}
