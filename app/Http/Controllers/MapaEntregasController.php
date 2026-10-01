<?php

namespace App\Http\Controllers;

use App\Models\AtribuicaoEntrega;
use App\Models\Corporate;
use App\Models\RegistoEntrega;
use App\Models\User;
use App\Models\WooOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Mapa da volta de um colaborador num dia: as paragens pela ordem definida nas
 * Rotas, o percurso no Google Maps (sem chave de API) e links para abrir a
 * volta inteira na app do Google Maps do telemovel.
 */
class MapaEntregasController extends Controller
{
    private const DIAS = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sabado',
    ];

    /** O Google Maps aceita a origem, o destino e ate 9 paragens pelo meio. */
    private const PARAGENS_POR_PARTE = 10;

    public function __invoke(): View
    {
        $user = auth()->user();
        $data = filled(request('data')) ? Carbon::parse(request('data'))->startOfDay() : now()->startOfDay();

        // Os colaboradores so veem a sua volta; o admin pode ver a de qualquer um.
        $colaboradores = $user->isAdmin() ? User::where('ativo', true)->orderBy('name')->get() : collect();
        $colaborador = $user->isAdmin() && filled(request('user_id'))
            ? User::findOrFail((int) request('user_id'))
            : $user;

        $paragens = $this->paragens($colaborador, $data);
        $porFazer = request('todas') ? $paragens : $paragens->where('estado', '!=', 'entregue')->values();
        $comMorada = $porFazer->filter(fn (array $paragem): bool => filled($paragem['morada']))->values();
        $origem = trim((string) config('entregas.origem_rota'));

        $partes = $comMorada->chunk(self::PARAGENS_POR_PARTE)->values()->map(function (Collection $parte, int $i) use ($comMorada, $origem): array {
            // Cada parte comeca onde a anterior acabou. A primeira sai do
            // armazem (se estiver configurado) ou de onde o colaborador estiver.
            $partida = $i === 0 ? ($origem ?: null) : $comMorada[$i * self::PARAGENS_POR_PARTE - 1]['morada'];

            return [
                'paragens' => $parte->values(),
                'navegar' => $this->urlNavegacao($partida, $parte->pluck('morada')->all()),
                'embed' => $this->urlEmbed($partida, $parte->pluck('morada')->all()),
            ];
        });

        return view('entregas.mapa', [
            'data' => $data->toDateString(),
            'dia' => self::DIAS[$data->dayOfWeek] ?? null,
            'colaborador' => $colaborador,
            'colaboradores' => $colaboradores,
            'paragens' => $paragens,
            'partes' => $partes,
            'semMorada' => $porFazer->filter(fn (array $paragem): bool => blank($paragem['morada']))->values(),
            'todas' => (bool) request('todas'),
            'origem' => $origem,
        ]);
    }

    /** Paragens do colaborador nesse dia, pela ordem da volta. */
    private function paragens(User $colaborador, Carbon $data): Collection
    {
        $dia = self::DIAS[$data->dayOfWeek] ?? null;

        $atribuicoes = AtribuicaoEntrega::with(['corporate', 'wooOrder'])
            ->where('user_id', $colaborador->id)
            ->get()
            ->filter(fn (AtribuicaoEntrega $atribuicao): bool => $atribuicao->tipo === 'b2c'
                ? $atribuicao->wooOrder !== null && $atribuicao->dia_semana === $dia && $atribuicao->wooOrder->temEntregaB2cNaData($data)
                : $atribuicao->corporate !== null && $atribuicao->corporate->ativo && $atribuicao->corporate->diaEntregaOriginalParaData($data) === $atribuicao->dia_semana);

        // So se le o estado; a pagina nao cria registos de entrega.
        $estados = RegistoEntrega::query()
            ->where('user_id', $colaborador->id)
            ->whereDate('data_entrega', $data->toDateString())
            ->get()
            ->mapWithKeys(fn (RegistoEntrega $registo): array => [
                ($registo->tipo === 'b2c' ? 'b'.$registo->woo_order_id : 'c'.$registo->corporate_id) => $registo,
            ]);

        return $atribuicoes
            ->map(function (AtribuicaoEntrega $atribuicao) use ($estados): array {
                $linha = $atribuicao->tipo === 'b2c'
                    ? $this->paragemB2c($atribuicao->wooOrder)
                    : $this->paragemCorporate($atribuicao->corporate);
                $registo = $estados->get($linha['chave']);

                return $linha + [
                    'ordem' => $atribuicao->ordem,
                    'estado' => $registo?->status ?? 'pendente',
                    'registo' => $registo,
                ];
            })
            ->sortBy(fn (array $paragem): string => sprintf('%06d|%s', $paragem['ordem'] ?? 999999, mb_strtolower($paragem['nome'])))
            ->values();
    }

    private function paragemCorporate(Corporate $corporate): array
    {
        return [
            'chave' => 'c'.$corporate->id,
            'tipo' => 'corporate',
            'nome' => trim($corporate->empresa.($corporate->sucursal ? ' · '.$corporate->sucursal : '')),
            'morada' => $this->juntarMorada($corporate->moradaParaEntrega(), $corporate->cp_entrega, $corporate->cidade_entrega),
            'contacto' => trim(($corporate->responsavel_nome ?? '').' '.($corporate->responsavel_telefone ?? '')),
            'telefone' => $corporate->responsavel_telefone,
            'horario' => $corporate->horario_entrega,
        ];
    }

    private function paragemB2c(WooOrder $order): array
    {
        $shipping = (array) ($order->raw_payload['shipping'] ?? []);
        $billing = (array) ($order->raw_payload['billing'] ?? []);
        $bloco = filled($shipping['address_1'] ?? null) ? $shipping : $billing;

        return [
            'chave' => 'b'.$order->id,
            'tipo' => 'b2c',
            'nome' => '#'.$order->woo_id.' '.($order->billing_name ?: 'Cliente B2C'),
            'morada' => $this->juntarMorada(
                trim(($bloco['address_1'] ?? '').' '.($bloco['address_2'] ?? '')),
                $bloco['postcode'] ?? null,
                $bloco['city'] ?? null,
            ),
            'contacto' => $order->billing_phone ?: $order->billing_email,
            'telefone' => $order->billing_phone,
            'horario' => null,
        ];
    }

    /** Morada completa para o Google: rua, codigo postal e localidade, sem repetir. */
    private function juntarMorada(?string $morada, ?string $cp, ?string $cidade): ?string
    {
        $morada = trim(preg_replace('/\s+/', ' ', (string) $morada));
        $partes = $morada !== '' ? [$morada] : [];

        foreach ([trim((string) $cp), trim((string) $cidade)] as $extra) {
            if ($extra !== '' && ! str_contains(mb_strtolower($morada), mb_strtolower($extra))) {
                $partes[] = $extra;
            }
        }

        // Sem rua nao ha paragem: so com codigo postal o pino ficava no meio da zona.
        return $morada === '' ? null : implode(', ', $partes).', Portugal';
    }

    /** Link que abre a navegacao na app do Google Maps, com as paragens pela ordem. */
    private function urlNavegacao(?string $partida, array $moradas): string
    {
        $destino = array_pop($moradas);
        $parametros = array_filter([
            'api' => 1,
            'origin' => $partida,
            'destination' => $destino,
            'waypoints' => $moradas ? implode('|', $moradas) : null,
            'travelmode' => 'driving',
        ], fn ($valor): bool => $valor !== null);

        return 'https://www.google.com/maps/dir/?'.http_build_query($parametros);
    }

    /** Mapa com o percurso para mostrar dentro da pagina (nao precisa de chave). */
    private function urlEmbed(?string $partida, array $moradas): string
    {
        $inicio = $partida ?? array_shift($moradas);

        if ($moradas === []) {
            return 'https://maps.google.com/maps?'.http_build_query(['q' => $inicio, 'output' => 'embed']);
        }

        return 'https://maps.google.com/maps?'.http_build_query([
            'saddr' => $inicio,
            'daddr' => implode(' to:', $moradas),
            'output' => 'embed',
        ]);
    }
}
