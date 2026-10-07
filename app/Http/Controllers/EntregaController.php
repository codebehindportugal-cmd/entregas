<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkAtribuicaoEntregaRequest;
use App\Http\Requests\StoreAtribuicaoEntregaRequest;
use App\Http\Requests\UpdateRegistoEntregaRequest;
use App\Models\AtribuicaoEntrega;
use App\Models\Corporate;
use App\Models\CorporateHistorico;
use App\Models\PreparacaoItem;
use App\Models\RegistoEntrega;
use App\Models\User;
use App\Models\Viatura;
use App\Models\WooOrder;
use App\Models\Zona;
use App\Services\EntregasDoDia;
use App\Services\Geolocalizador;
use App\Services\OrganizadorDeVoltas;
use App\Services\ListaCabazResolver;
use App\Services\ComprasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class EntregaController extends Controller
{
    private const DIAS = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sabado',
    ];

    /**
     * Pagina de rotas: todas as entregas do dia (empresas + B2C) numa lista
     * unica, com a zona de cada uma, e a volta de cada zona ao lado com quem a
     * faz nesse dia. A pesquisa e os filtros sao feitos no browser.
     */
    public function index(): View
    {
        $dia = request('dia', self::DIAS[now()->dayOfWeek] ?? 'Segunda');
        $dia = in_array($dia, self::DIAS, true) ? $dia : 'Segunda';
        $dataB2c = $this->dataReferenciaParaDia($dia);

        [$atribuicoes, $entregas] = $this->entregasDoDiaParaRotas($dia, $dataB2c);

        // Uma volta por zona ativa (mesmo vazia), mais zonas desativadas que
        // ainda tenham entregas neste dia.
        $zonas = Zona::with(['horarios', 'substituicoes.user'])
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get()
            ->filter(fn (Zona $zona): bool => $zona->ativo || $atribuicoes->contains('zona_id', $zona->id))
            ->values();
        $porZona = $atribuicoes->whereNotNull('zona_id')->groupBy('zona_id');

        $organizador = app(OrganizadorDeVoltas::class);

        $rotas = $zonas->map(function (Zona $zona) use ($porZona, $dataB2c, $dia, $organizador): array {
            $paragens = $this->paragensDaZona($porZona->get($zona->id) ?? collect());
            // Horas previstas pela ordem atual (estimativa, para ver se ha
            // entregas fora de horas). Uma volta por pessoa: em semanas com
            // feriado a mesma zona pode ter entregas de duas pessoas.
            $previsao = $paragens
                ->groupBy(fn (array $paragem): int => $this->quemFaz($paragem['atribuicao'], $dataB2c))
                ->flatMap(fn ($grupo) => $organizador->simular($this->paraOrganizar($grupo), $zona->partida_cp))
                ->keyBy('chave');

            return [
                'zona' => $zona,
                // Em semanas com feriado as entregas deste dia podem ser as do dia
                // anterior empurradas: vale quem faz a zona no dia original delas.
                'colaborador' => $zona->colaboradorEm($dataB2c, ($porZona->get($zona->id) ?? collect())->first()?->dia_semana ?? $dia),
                'substituicao' => $zona->substituicaoEm($dataB2c),
                'paragens' => $paragens->map(fn (array $paragem): array => $paragem + ['previsao' => $previsao->get($paragem['atribuicao']->id)]),
                'saida' => $previsao->pluck('saida')->filter()->unique()->sort()->implode(' e '),
                'atrasadas' => $previsao->where('atrasada', true)->count(),
            ];
        })->values();

        return view('entregas.index', [
            'dia' => $dia,
            'dias' => array_values(self::DIAS),
            'dataDia' => $dataB2c->toDateString(),
            'semana' => $this->semanaPedida(),
            'entregas' => $entregas,
            'rotas' => $rotas,
            'zonas' => $zonas->where('ativo', true)->values(),
            'porConverter' => $atribuicoes->whereNull('zona_id')->whereNotNull('user_id')->count(),
            'parceirosLocais' => Corporate::where('ativo', true)->where('parceiro_local', true)->get()
                ->filter(fn (Corporate $corporate): bool => $corporate->temEntregaNaData($dataB2c))
                ->pluck('empresa')
                ->values(),
        ]);
    }

    /**
     * As entregas do dia para a pagina das Rotas: as atribuicoes desse dia e
     * a lista de todas as entregas (com zona, ou com a zona sugerida pelo
     * codigo postal quando ainda nao tem).
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    private function entregasDoDiaParaRotas(string $dia, Carbon $dataB2c): array
    {
        app(EntregasDoDia::class)->garantirZonas($dataB2c);

        $corporatesDoDia = Corporate::where('ativo', true)
            ->where('parceiro_local', false)
            ->orderBy('empresa')
            ->get()
            ->filter(fn (Corporate $corporate): bool => $corporate->temEntregaNaData($dataB2c))
            ->values();

        $b2cOrders = $this->b2cOrdersParaDia($dia, $dataB2c);

        $atribuicoes = AtribuicaoEntrega::with(['corporate', 'wooOrder', 'user', 'zona'])
            ->where(function ($query) use ($dia, $corporatesDoDia): void {
                $query->where(fn ($query) => $query->where('tipo', 'corporate')->whereIn('corporate_id', $corporatesDoDia->pluck('id')))
                    ->orWhere(fn ($query) => $query->where('tipo', 'b2c')->where('dia_semana', $dia));
            })
            ->get()
            ->filter(function (AtribuicaoEntrega $atribuicao) use ($dataB2c, $dia, $b2cOrders): bool {
                // So as encomendas com entrega nesta data: uma subscricao que ja
                // acabou (ou cuja proxima entrega e noutra semana) continua com
                // a atribuicao guardada, mas nao entra na volta.
                if ($atribuicao->tipo === 'b2c') {
                    return $atribuicao->wooOrder !== null
                        && $atribuicao->dia_semana === $dia
                        && $b2cOrders->contains('id', $atribuicao->woo_order_id);
                }

                return $atribuicao->corporate?->diaEntregaOriginalParaData($dataB2c) === $atribuicao->dia_semana;
            })
            ->sortBy(fn (AtribuicaoEntrega $atribuicao): string => sprintf(
                '%06d|%s',
                $atribuicao->ordem ?? 999999,
                mb_strtolower($this->nomeAtribuicao($atribuicao))
            ))
            ->values();

        $porCorporate = $atribuicoes->where('tipo', 'corporate')->keyBy('corporate_id');
        $porB2c = $atribuicoes->where('tipo', 'b2c')->keyBy('woo_order_id');

        $entregas = $corporatesDoDia
            ->map(fn (Corporate $corporate): array => $this->linhaEntregaCorporate($corporate, $porCorporate->get($corporate->id)))
            ->concat($b2cOrders->map(fn (WooOrder $order): array => $this->linhaEntregaB2c($order, $porB2c->get($order->id))))
            // Ordenar por codigo postal junta as entregas da mesma zona,
            // que e o que se quer ver ao montar as rotas.
            ->sortBy(fn (array $linha): string => ($linha['cp'] ?: 'zzzz').'|'.mb_strtolower($linha['nome']))
            ->values();

        $zonasAtivas = Zona::where('ativo', true)->orderBy('ordem')->get();
        $entregas = $entregas->map(function (array $linha) use ($zonasAtivas): array {
            $sugerida = $linha['zona_id'] === null ? Zona::sugeridaPara($linha['cp'] ?: $this->codigoPostalNaMorada($linha['morada']), $zonasAtivas) : null;

            return $linha + ['sugestao_id' => $sugerida?->id, 'sugestao_nome' => $sugerida?->nome];
        });

        return [$atribuicoes, $entregas];
    }

    private function codigoPostalNaMorada(?string $morada): ?string
    {
        return preg_match('/\b(\d{4})-\d{3}\b/', (string) $morada, $m) ? $m[0] : null;
    }

    /** Poe cada entrega sem zona deste dia na zona sugerida pelo codigo postal. */
    public function storeAtribuicoesSugeridas(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $dia = $request->validate(['dia_semana' => ['required', 'in:'.implode(',', self::DIAS)]])['dia_semana'];
        [, $entregas] = $this->entregasDoDiaParaRotas($dia, $this->dataReferenciaParaDia($dia));
        $sugeridas = $entregas->whereNull('zona_id')->whereNotNull('sugestao_id');

        DB::transaction(function () use ($sugeridas, $dia): void {
            $sugeridas->each(fn (array $linha) => $this->atribuir($linha['tipo'], (int) $linha['id'], $dia, (int) $linha['sugestao_id'], automatica: true));
        });

        $total = $sugeridas->count();

        return back()->with('status', $total === 1 ? '1 entrega posta na zona sugerida.' : "{$total} entregas postas nas zonas sugeridas.");
    }

    private function linhaEntregaCorporate(Corporate $corporate, ?AtribuicaoEntrega $atribuicao): array
    {
        // Deixada noutra empresa (ex.: Evora nos Correos de Lisboa): conta a morada dessa.
        $local = $corporate->localDaVolta();

        return [
            'chave' => 'c'.$corporate->id,
            'tipo' => 'corporate',
            'id' => $corporate->id,
            'nome' => trim($corporate->empresa.($corporate->sucursal ? ' · '.$corporate->sucursal : '')),
            'morada' => $local->moradaParaEntrega(),
            'cp' => trim((string) $local->cp_entrega),
            'localidade' => trim((string) $local->cidade_entrega),
            'detalhe' => implode(' · ', array_filter([
                $corporate->horario_entrega,
                $local->isNot($corporate) ? 'deixar em '.trim($local->empresa.($local->sucursal ? ' '.$local->sucursal : '')) : null,
            ])) ?: null,
            'horario' => $corporate->horario_entrega,
        ] + $this->zonaDaLinha($atribuicao);
    }

    private function linhaEntregaB2c(WooOrder $order, ?AtribuicaoEntrega $atribuicao): array
    {
        $endereco = $order->enderecoDeEntrega();

        return [
            'chave' => 'b'.$order->id,
            'tipo' => 'b2c',
            'id' => $order->id,
            'nome' => '#'.$order->woo_id.' '.($order->billing_name ?: 'Sem nome'),
            'morada' => $endereco['morada'],
            'cp' => $endereco['cp'],
            'localidade' => $endereco['localidade'],
            'detalhe' => $order->billing_phone ?: $order->billing_email,
        ] + $this->zonaDaLinha($atribuicao);
    }

    private function zonaDaLinha(?AtribuicaoEntrega $atribuicao): array
    {
        return [
            'zona_id' => $atribuicao?->zona_id,
            'zona_nome' => $atribuicao?->zona?->nome,
            // Atribuida a um colaborador antes das zonas e ainda por converter.
            'antes' => $atribuicao?->zona_id === null ? $atribuicao?->user?->name : null,
        ];
    }

    public function verificacao(Request $request): View
    {
        $dataSelecionada = filled($request->input('data'))
            ? Carbon::parse($request->input('data'))
            : now();

        $data = $dataSelecionada->toDateString();
        $periodo = $request->string('periodo')->toString() ?: 'dia';
        $inicioPeriodo = match ($periodo) {
            'semana' => $dataSelecionada->copy()->startOfWeek(),
            'mes' => $dataSelecionada->copy()->startOfMonth(),
            default => $dataSelecionada->copy()->startOfDay(),
        };
        $fimPeriodo = match ($periodo) {
            'semana' => $dataSelecionada->copy()->endOfWeek(),
            'mes' => $dataSelecionada->copy()->endOfMonth(),
            default => $dataSelecionada->copy()->endOfDay(),
        };
        $dia = self::DIAS[$dataSelecionada->dayOfWeek] ?? null;
        $status = $request->string('status')->toString();
        $userId = $request->integer('user_id');
        $q = $request->string('q')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        $sortColumns = [
            'data' => 'registo_entregas.data_entrega',
            'empresa' => 'corporates.empresa',
            'colaborador' => 'users.name',
            'estado' => 'registo_entregas.status',
            'hora' => 'registo_entregas.hora_entrega',
        ];
        $sortColumn = $sortColumns[$sort] ?? 'corporates.empresa';

        if ($dia !== null) {
            $corporatesComEntrega = Corporate::where('ativo', true)
                ->get()
                ->filter(fn (Corporate $corporate): bool => $corporate->temEntregaNaData($dataSelecionada))
                ->values();
            $corporateIdsComEntrega = $corporatesComEntrega->pluck('id');

            $b2cOrderIdsComEntrega = $this->b2cOrdersParaDia($dia, $dataSelecionada)->pluck('id');

            // Cria os registos do dia com quem faz cada zona nesse dia.
            $entregasDoDia = app(EntregasDoDia::class);
            $atribuicoesDoDia = $entregasDoDia->atribuicoes($dataSelecionada);

            DB::transaction(function () use ($atribuicoesDoDia, $entregasDoDia, $dataSelecionada, $data): void {
                $atribuicoesDoDia->each(function (AtribuicaoEntrega $atribuicao) use ($entregasDoDia, $dataSelecionada, $data): void {
                    $colaboradorId = $entregasDoDia->colaboradorId($atribuicao, $dataSelecionada);

                    if ($colaboradorId !== null) {
                        $this->registoPara($atribuicao, $data, $colaboradorId);
                    }
                });
            });
        }

        $corporateIdsComEntrega ??= collect();
        $b2cOrderIdsComEntrega ??= collect();

        $registos = RegistoEntrega::with(['corporate', 'wooOrder', 'user'])
            ->whereBetween('data_entrega', [$inicioPeriodo->toDateString(), $fimPeriodo->toDateString()])
            ->when($periodo === 'dia' && $dia !== null, fn ($query) => $query->where(function ($query) use ($corporateIdsComEntrega, $b2cOrderIdsComEntrega): void {
                $query->whereIn('corporate_id', $corporateIdsComEntrega)
                    ->orWhereIn('woo_order_id', $b2cOrderIdsComEntrega);
            }))
            ->when(in_array($status, ['pendente', 'entregue', 'falhou'], true), fn ($query) => $query->where('status', $status))
            ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
            ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                $query->where('corporates.empresa', 'like', "%{$q}%")
                    ->orWhere('corporates.sucursal', 'like', "%{$q}%")
                    ->orWhere('corporates.morada_entrega', 'like', "%{$q}%")
                    ->orWhere('corporates.fatura_morada', 'like', "%{$q}%")
                    ->orWhere('woo_orders.billing_name', 'like', "%{$q}%")
                    ->orWhere('woo_orders.billing_phone', 'like', "%{$q}%")
                    ->orWhere('woo_orders.billing_email', 'like', "%{$q}%")
                    ->orWhere('woo_orders.woo_id', 'like', "%{$q}%");
            }))
            ->leftJoin('corporates', 'registo_entregas.corporate_id', '=', 'corporates.id')
            ->leftJoin('woo_orders', 'registo_entregas.woo_order_id', '=', 'woo_orders.id')
            ->join('users', 'registo_entregas.user_id', '=', 'users.id')
            ->orderBy($sortColumn, $direction)
            ->orderBy('corporates.empresa')
            ->orderBy('woo_orders.billing_name')
            ->select('registo_entregas.*')
            ->get();

        $resumo = RegistoEntrega::whereBetween('data_entrega', [$inicioPeriodo->toDateString(), $fimPeriodo->toDateString()])
            ->when($periodo === 'dia' && $dia !== null, fn ($query) => $query->where(function ($query) use ($corporateIdsComEntrega, $b2cOrderIdsComEntrega): void {
                $query->whereIn('corporate_id', $corporateIdsComEntrega)
                    ->orWhereIn('woo_order_id', $b2cOrderIdsComEntrega);
            }))
            ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
            ->when(filled($q), fn ($query) => $query
                ->leftJoin('corporates', 'registo_entregas.corporate_id', '=', 'corporates.id')
                ->leftJoin('woo_orders', 'registo_entregas.woo_order_id', '=', 'woo_orders.id')
                ->where(function ($query) use ($q): void {
                    $query->where('corporates.empresa', 'like', "%{$q}%")
                        ->orWhere('corporates.sucursal', 'like', "%{$q}%")
                        ->orWhere('corporates.morada_entrega', 'like', "%{$q}%")
                        ->orWhere('corporates.fatura_morada', 'like', "%{$q}%")
                        ->orWhere('woo_orders.billing_name', 'like', "%{$q}%")
                        ->orWhere('woo_orders.billing_phone', 'like', "%{$q}%")
                        ->orWhere('woo_orders.billing_email', 'like', "%{$q}%")
                        ->orWhere('woo_orders.woo_id', 'like', "%{$q}%");
                })
            )
            ->selectRaw("sum(case when status = 'pendente' then 1 else 0 end) as pendentes")
            ->selectRaw("sum(case when status = 'entregue' then 1 else 0 end) as entregues")
            ->selectRaw("sum(case when status = 'falhou' then 1 else 0 end) as falhadas")
            ->first();

        return view('entregas.verificacao', [
            'data' => $data,
            'periodo' => $periodo,
            'inicioPeriodo' => $inicioPeriodo->toDateString(),
            'fimPeriodo' => $fimPeriodo->toDateString(),
            'dia' => $dia,
            'status' => $status,
            'userId' => $userId,
            'q' => $q,
            'sort' => $sort ?: 'empresa',
            'direction' => $direction,
            'registos' => $registos,
            'resumo' => $resumo,
            'colaboradores' => User::where('ativo', true)->orderBy('name')->get(),
        ]);
    }

    public function preparacao(Request $request): View
    {
        $inicio = filled($request->input('inicio'))
            ? Carbon::parse($request->input('inicio'))->startOfDay()
            : (filled($request->input('data'))
                ? Carbon::parse($request->input('data'))->startOfDay()
                : now()->startOfDay());
        $fim = filled($request->input('fim'))
            ? Carbon::parse($request->input('fim'))->startOfDay()
            : $inicio->copy();

        if ($fim->lessThan($inicio)) {
            $fim = $inicio->copy();
        }

        if ($inicio->diffInDays($fim) > 14) {
            $fim = $inicio->copy()->addDays(14);
        }

        $diaFiltro = $request->string('dia')->toString();

        if (! in_array($diaFiltro, self::DIAS, true)) {
            $diaFiltro = '';
        }

        $data = $inicio->toDateString();
        $dia = $diaFiltro ?: 'Todos';
        $datasSelecionadas = $this->datasPreparacao($inicio, $fim, $diaFiltro);

        if ($datasSelecionadas->isEmpty()) {
            $datasSelecionadas = collect([$inicio->copy()]);
        }

        // As entregas novas destes dias entram ja na zona do codigo postal,
        // senao as empresas sem zona ficavam escondidas da preparacao.
        $entregasDoDia = app(EntregasDoDia::class);
        $datasSelecionadas->each(fn (Carbon $data) => $entregasDoDia->garantirZonas($data->copy()));

        $q = $request->string('q')->toString();
        $corporatePreparacoes = collect();
        $b2cPreparacoes = collect();
        $historicosEntrega = CorporateHistorico::with('corporate')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->whereIn('tipo', ['nao_entregamos', 'entrega_parcial', 'entrega_extra'])
            ->whereHas('corporate', fn ($query) => $query
                ->where('ativo', true)
                ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                    $query->where('empresa', 'like', "%{$q}%")
                        ->orWhere('sucursal', 'like', "%{$q}%")
                        ->orWhere('morada_entrega', 'like', "%{$q}%")
                        ->orWhere('fatura_morada', 'like', "%{$q}%");
                }))
            )
            ->get()
            ->groupBy(fn (CorporateHistorico $historico): string => $historico->data->toDateString());

        foreach ($datasSelecionadas as $dataSelecionada) {
            $diaData = self::DIAS[$dataSelecionada->dayOfWeek] ?? null;

            if ($diaData === null) {
                continue;
            }

            $dataKey = $dataSelecionada->toDateString();
            $historicosDoDia = $historicosEntrega->get($dataKey, collect())->groupBy('corporate_id');

            $corporatesDoDia = Corporate::where('ativo', true)
                ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                    $query->where('empresa', 'like', "%{$q}%")
                        ->orWhere('sucursal', 'like', "%{$q}%")
                        ->orWhere('morada_entrega', 'like', "%{$q}%")
                        ->orWhere('fatura_morada', 'like', "%{$q}%");
                }))
                ->orderBy('empresa')
                ->get()
                ->filter(fn (Corporate $corporate) => $corporate->temEntregaNaData($dataSelecionada))
                ->values();

            $corporatesDoDia->each(function (Corporate $corporate) use ($corporatePreparacoes, $dataSelecionada, $historicosDoDia): void {
                $historicosCorporate = $historicosDoDia->get($corporate->id, collect());
                $diaOriginal = $corporate->diaEntregaOriginalParaData($dataSelecionada);

                if ($diaOriginal === null) {
                    return;
                }

                $corporatePreparacoes->push([
                    'data' => $dataSelecionada->toDateString(),
                    'dia' => $diaOriginal,
                    'corporate' => $corporate,
                    'entrega_regular' => true,
                    ...$this->resumoHistoricosPreparacao($historicosCorporate, $corporate->totalPecasParaDia($diaOriginal), true),
                ]);
            });

            $corporateIdsRegulares = $corporatesDoDia->pluck('id');
            $historicosDoDia
                ->reject(fn ($historicosCorporate, $corporateId): bool => $corporateIdsRegulares->contains($corporateId))
                ->filter(fn ($historicosCorporate): bool => $historicosCorporate->contains(fn (CorporateHistorico $historico): bool => $historico->tipo === 'entrega_extra'))
                ->each(function ($historicosCorporate) use ($corporatePreparacoes, $dataSelecionada, $diaData): void {
                    $corporate = $historicosCorporate->first()?->corporate;

                    if ($corporate === null) {
                        return;
                    }

                    $corporatePreparacoes->push([
                        'data' => $dataSelecionada->toDateString(),
                        'dia' => $diaData,
                        'corporate' => $corporate,
                        'entrega_regular' => false,
                        ...$this->resumoHistoricosPreparacao($historicosCorporate, 0, false),
                    ]);
                });

            $this->b2cOrdersParaDia($diaData, $dataSelecionada, $q)
                ->each(function (WooOrder $order) use ($b2cPreparacoes, $dataSelecionada, $diaData): void {
                    $b2cPreparacoes->push([
                        'data' => $dataSelecionada->toDateString(),
                        'dia' => $diaData,
                        'order' => $order,
                    ]);
                });
        }

        // Das EMPRESAS so se prepara o que tem rota atribuida e nao foi dado
        // como nao entregue (pedido do Andre, 28/08/2026). Os clientes B2C
        // aparecem sempre; deles so se escondem os nao entregues. O filtro pode
        // ser desligado com ?mostrar_tudo=1, para nao esconder trabalho.
        $mostrarTudo = $request->boolean('mostrar_tudo');

        // Com rota = com zona atribuida.
        $atribuicoes = AtribuicaoEntrega::query()
            ->select(['tipo', 'corporate_id', 'woo_order_id', 'dia_semana'])
            ->whereNotNull('zona_id')
            ->get();

        $temColaboradorCorporate = $atribuicoes
            ->where('tipo', 'corporate')
            ->map(fn (AtribuicaoEntrega $a): string => $a->corporate_id.'|'.$a->dia_semana)
            ->flip();

        $escondidasSemColaborador = 0;
        $escondidasNaoEntregues = 0;

        // "Nao entregue" tem duas origens: o historico da empresa
        // (nao_entregamos, planeado) e o registo da rota marcado como falhou
        // pelo colaborador. As duas escondem a linha da preparacao.
        $falhadas = RegistoEntrega::query()
            ->where('status', 'falhou')
            ->whereBetween('data_entrega', [$inicio->toDateString(), $fim->toDateString()])
            ->get(['tipo', 'corporate_id', 'woo_order_id', 'data_entrega']);

        $falhadasCorporate = $falhadas
            ->where('tipo', 'corporate')
            ->map(fn (RegistoEntrega $registo): string => $registo->corporate_id.'|'.$registo->data_entrega->toDateString())
            ->flip();

        $falhadasB2c = $falhadas
            ->where('tipo', 'b2c')
            ->map(fn (RegistoEntrega $registo): string => $registo->woo_order_id.'|'.$registo->data_entrega->toDateString())
            ->flip();

        if (! $mostrarTudo) {
            $antesCorporate = $corporatePreparacoes->count();

            $corporatePreparacoes = $corporatePreparacoes
                ->reject(function (array $preparacao) use (&$escondidasNaoEntregues, $falhadasCorporate): bool {
                    $naoEntregamos = str_contains((string) ($preparacao['tipo_entrega'] ?? ''), 'Nao entregamos');
                    $falhou = $falhadasCorporate->has($preparacao['corporate']->id.'|'.$preparacao['data']);

                    if ($naoEntregamos || $falhou) {
                        $escondidasNaoEntregues++;

                        return true;
                    }

                    return false;
                })
                ->filter(fn (array $preparacao): bool => $temColaboradorCorporate->has($preparacao['corporate']->id.'|'.$preparacao['dia']))
                ->values();

            $escondidasSemColaborador += max(0, $antesCorporate - $corporatePreparacoes->count() - $escondidasNaoEntregues);

            $naoEntreguesB2c = 0;

            // Os clientes B2C aparecem SEMPRE, tenham ou nao colaborador
            // atribuido (pedido do Andre, 01/09/2026) — so se escondem as
            // entregas dadas como nao entregues.
            $b2cPreparacoes = $b2cPreparacoes
                ->reject(function (array $preparacao) use (&$naoEntreguesB2c, $falhadasB2c): bool {
                    if ($falhadasB2c->has($preparacao['order']->id.'|'.$preparacao['data'])) {
                        $naoEntreguesB2c++;

                        return true;
                    }

                    return false;
                })
                ->values();

            $escondidasNaoEntregues += $naoEntreguesB2c;
        }

        $corporatePreparacoes->each(function (array $preparacao): void {
            PreparacaoItem::firstOrCreate([
                'data_preparacao' => $preparacao['data'],
                'tipo' => 'corporate',
                'corporate_id' => $preparacao['corporate']->id,
            ]);
        });

        $b2cPreparacoes->each(function (array $preparacao): void {
            PreparacaoItem::firstOrCreate([
                'data_preparacao' => $preparacao['data'],
                'tipo' => 'b2c',
                'woo_order_id' => $preparacao['order']->id,
            ]);
        });

        $corporateIds = $corporatePreparacoes->pluck('corporate.id')->filter()->unique()->values();
        // A picagem de cada encomenda sai da composicao do tamanho que o
        // cliente assina (Listas de cabazes), nao do nome do produto do site.
        $resolverListas = new ListaCabazResolver;

        $b2cPreparacoes = $b2cPreparacoes->map(function (array $preparacao) use ($resolverListas): array {
            return [...$preparacao, 'picagem' => $resolverListas->picagemB2c($preparacao['order'], $preparacao['data'])];
        });

        $b2cOrderIds = $b2cPreparacoes->pluck('order.id')->filter()->unique()->values();

        $preparacaoItems = PreparacaoItem::with(['corporate', 'wooOrder', 'feitoPor'])
            ->whereBetween('data_preparacao', [$inicio->toDateString(), $fim->toDateString()])
            ->where(function ($query) use ($corporateIds, $b2cOrderIds): void {
                $query->whereIn('corporate_id', $corporateIds)
                    ->orWhereIn('woo_order_id', $b2cOrderIds);
            })
            ->get()
            ->keyBy(fn (PreparacaoItem $item) => $item->tipo.'-'.($item->corporate_id ?: $item->woo_order_id).'-'.$item->data_preparacao->toDateString());

        $produtosKg = ComprasService::PRODUTOS_KG;
        $totaisFrutas = collect(ComprasService::FRUTAS)
            ->mapWithKeys(fn (string $label, string $fruta) => [
                $fruta => $corporatePreparacoes->sum(fn (array $preparacao) => ($preparacao['usar_produtos'] ?? true) && in_array($fruta, $produtosKg, true)
                    ? (float) ($preparacao['corporate']->frutasParaDia($preparacao['dia'])[$fruta] ?? 0)
                    : (($preparacao['usar_produtos'] ?? true) ? (int) ($preparacao['corporate']->frutasParaDia($preparacao['dia'])[$fruta] ?? 0) : 0)),
            ])
            ->all();
        $totalPecas = collect($totaisFrutas)
            ->except($produtosKg)
            ->sum(fn (int|float $quantidade): int => (int) $quantidade);
        $totaisPastelaria = collect(ComprasService::PASTELARIA)
            ->mapWithKeys(fn (string $label, string $produto) => [
                $produto => $corporatePreparacoes->sum(fn (array $preparacao) => ($preparacao['usar_produtos'] ?? true) ? (int) ($preparacao['corporate']->pastelariaPorDia($preparacao['dia'])[$produto] ?? 0) : 0),
            ])
            ->all();

        return view('entregas.preparacao', [
            'data' => $data,
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'dia' => $dia,
            'diaFiltro' => $diaFiltro,
            'periodoLabel' => $inicio->isSameDay($fim)
                ? $inicio->format('d/m/Y')
                : $inicio->format('d/m/Y').' a '.$fim->format('d/m/Y'),
            'q' => $q,
            'dias' => array_values(self::DIAS),
            'corporatePreparacoes' => $corporatePreparacoes,
            'b2cPreparacoes' => $b2cPreparacoes,
            'mostrarTudo' => $mostrarTudo,
            'escondidasSemColaborador' => $escondidasSemColaborador,
            'escondidasNaoEntregues' => $escondidasNaoEntregues,
            'viaturas' => Viatura::query()->ativa()->orderBy('ordem')->orderBy('matricula')->get(),
            'preparacaoItems' => $preparacaoItems,
            'totalCaixas' => $corporatePreparacoes->sum(fn (array $preparacao) => (int) $preparacao['corporate']->numero_caixas),
            'totalPecas' => $totalPecas,
            'totalPecasEntregues' => $corporatePreparacoes->sum(fn (array $preparacao) => (int) ($preparacao['pecas_entregues'] ?? 0)),
            'totaisFrutas' => $totaisFrutas,
            'totaisPastelaria' => $totaisPastelaria,
            'totalFeitos' => $preparacaoItems->where('feito', true)->count(),
            'totalPorFazer' => $preparacaoItems->where('feito', false)->count(),
        ]);
    }

    private function resumoHistoricosPreparacao($historicos, int $pecasRegulares, bool $entregaRegular): array
    {
        $historicoNaoEntregamos = $historicos->first(fn (CorporateHistorico $historico): bool => $historico->tipo === 'nao_entregamos');
        $historicoParcial = $historicos->first(fn (CorporateHistorico $historico): bool => $historico->tipo === 'entrega_parcial');
        $historicosExtra = $historicos->filter(fn (CorporateHistorico $historico): bool => $historico->tipo === 'entrega_extra');
        $pecasExtra = $historicosExtra->sum(fn (CorporateHistorico $historico): int => (int) ($historico->pecas_entregues ?? 0));

        if ($historicoNaoEntregamos !== null) {
            return [
                'tipo_entrega' => 'Nao entregamos'.($pecasExtra > 0 ? ' + entrega extra' : ''),
                'pecas_entregues' => $pecasExtra,
                'usar_produtos' => false,
            ];
        }

        if ($historicoParcial !== null) {
            return [
                'tipo_entrega' => 'Entrega parcial'.($pecasExtra > 0 ? ' + extra' : ''),
                'pecas_entregues' => (int) ($historicoParcial->pecas_entregues ?? 0) + $pecasExtra,
                'usar_produtos' => true,
            ];
        }

        if ($pecasExtra > 0 && ! $entregaRegular) {
            return [
                'tipo_entrega' => 'Entrega extra',
                'pecas_entregues' => $pecasExtra,
                'usar_produtos' => false,
            ];
        }

        return [
            'tipo_entrega' => $pecasExtra > 0 ? 'Entrega regular + extra' : 'Entrega regular',
            'pecas_entregues' => $pecasRegulares + $pecasExtra,
            'usar_produtos' => true,
        ];
    }

    private function datasPreparacao(Carbon $inicio, Carbon $fim, string $diaFiltro)
    {
        $datas = collect();
        $data = $inicio->copy();

        while ($data->lessThanOrEqualTo($fim)) {
            $diaData = self::DIAS[$data->dayOfWeek] ?? null;

            if ($diaData !== null && ($diaFiltro === '' || $diaFiltro === $diaData)) {
                $datas->push($data->copy());
            }

            $data->addDay();
        }

        return $datas;
    }

    public function updatePreparacaoItem(Request $request, PreparacaoItem $item, \App\Services\GuiaTransporteService $guias): RedirectResponse
    {
        $feito = $request->boolean('feito');
        $anchor = $request->string('anchor')->toString();
        $matricula = $request->string('matricula')->toString();

        $item->update([
            'feito' => $feito,
            'feito_at' => $feito ? now() : null,
            'feito_por' => $feito ? auth()->id() : null,
            'matricula' => $matricula !== '' ? $matricula : $item->matricula,
        ]);

        $aviso = null;
        $avisos = [];

        // Guias ja registadas que foram APAGADAS ou ANULADAS no Moloni deixam de
        // bloquear: limpa-se o registo e emite-se outra. Se ainda existirem,
        // diz-se que ja existem (antes nao dizia nada).
        if ($feito && $item->tipo === 'corporate' && $item->corporate) {
            foreach (['guia_document_id' => 'guia de transporte', 'remessa_document_id' => 'guia de remessa'] as $campo => $nomeDoc) {
                if (! $item->{$campo}) {
                    continue;
                }

                $valido = $guias->documentoValido((int) $item->{$campo});

                if ($valido === false) {
                    $item->update([$campo => null]);
                } elseif ($valido === true) {
                    $avisos[] = 'A '.$nomeDoc.' desta entrega ja tinha sido emitida (#'.$item->{$campo}.'). Para emitir outra, apaga ou anula essa no Moloni e volta a marcar.';
                } else {
                    $avisos[] = 'Nao consegui confirmar no Moloni a '.$nomeDoc.' #'.$item->{$campo}.' — nao foi emitida outra.';
                }
            }
        }

        // Ao terminar a preparacao de uma entrega corporate, emite a guia de
        // transporte com os produtos do dia (uma vez).
        if ($feito && $item->tipo === 'corporate' && $item->corporate && ! $item->guia_document_id) {
            $matriculaGuia = $matricula !== '' ? $matricula : (string) $item->matricula;

            if ($matriculaGuia === '') {
                $aviso = 'Preparacao marcada, mas falta a matricula para emitir a guia de transporte.';
            } else {
                try {
                    $data = $item->data_preparacao instanceof \Illuminate\Support\Carbon
                        ? $item->data_preparacao->copy()
                        : \Illuminate\Support\Carbon::parse($item->data_preparacao);

                    $resultado = $guias->emitirGuiaCorporate($item->corporate, $data, $matriculaGuia);
                    $item->update(['guia_document_id' => $resultado['document_id']]);
                    $avisos[] = 'Guia de transporte emitida (#'.$resultado['document_id'].').';
                } catch (\Throwable $e) {
                    $aviso = 'Preparacao marcada, mas a guia de transporte falhou: '.$e->getMessage();
                }
            }
        }

        // Sucursais entregues por terceiros levam tambem guia de remessa. Vai
        // em separado da de transporte: se uma falhar, a outra fica na mesma.
        if ($feito && $item->tipo === 'corporate' && $item->corporate?->guia_remessa && ! $item->remessa_document_id) {
            try {
                $data = $item->data_preparacao instanceof \Illuminate\Support\Carbon
                    ? $item->data_preparacao->copy()
                    : \Illuminate\Support\Carbon::parse($item->data_preparacao);

                $resultado = $guias->emitirGuiaRemessaCorporate($item->corporate, $data);
                $item->update(['remessa_document_id' => $resultado['document_id']]);
            } catch (\Throwable $e) {
                $aviso = trim(($aviso ?? '').' Guia de remessa falhou: '.$e->getMessage());
            }
        }

        $redirect = $this->redirectBackToAnchor($anchor);

        if ($aviso !== null || $avisos !== []) {
            return $redirect->with('status', trim(implode(' ', array_filter(array_merge(
                [$aviso ?? 'Preparacao marcada como feita.'],
                $avisos,
            )))));
        }

        return $redirect->with('status', $feito ? 'Preparacao marcada como feita.' : 'Preparacao marcada como por fazer.');
    }

    public function updatePreparacaoProdutos(Request $request, PreparacaoItem $item): RedirectResponse
    {
        abort_unless($item->tipo === 'b2c', 404);

        $data = $request->validate([
            'produtos_picados' => ['nullable', 'array'],
            'produtos_picados.*' => ['string'],
        ]);

        $anchor = $request->string('anchor')->toString();
        $picados = array_values(array_unique($data['produtos_picados'] ?? []));

        // O total a picar sao as linhas da composicao do cabaz mais os extras
        // da encomenda — as mesmas que a preparacao mostra.
        $totalProdutos = $item->wooOrder !== null
            ? count((new ListaCabazResolver)->picagemB2c($item->wooOrder, $item->data_preparacao)['linhas'])
            : 0;

        $feito = $totalProdutos === 0 || count($picados) >= $totalProdutos;

        $item->update([
            'produtos_picados' => $picados,
            'feito' => $feito,
            'feito_at' => $feito ? now() : null,
            'feito_por' => $feito ? auth()->id() : null,
        ]);

        return $this->redirectBackToAnchor($anchor)
            ->with('status', $feito ? 'Encomenda B2C preparada.' : 'Produtos picados guardados.');
    }

    private function redirectBackToAnchor(string $anchor): RedirectResponse
    {
        $url = preg_replace('/#.*/', '', url()->previous()) ?: url()->previous();

        if (filled($anchor)) {
            $url .= '#'.rawurlencode($anchor);
        }

        return redirect()->to($url);
    }

    public function storeAtribuicao(StoreAtribuicaoEntregaRequest $request): RedirectResponse
    {
        $this->atribuir(
            $request->validated('tipo'),
            (int) ($request->validated('tipo') === 'corporate' ? $request->validated('corporate_id') : $request->validated('woo_order_id')),
            $request->validated('dia_semana'),
            (int) $request->validated('zona_id'),
        );

        return back()->with('status', 'Atribuicao guardada.');
    }

    public function storeAtribuicoesBulk(BulkAtribuicaoEntregaRequest $request): RedirectResponse
    {
        $dia = $request->validated('dia_semana');
        $zonaId = (int) $request->validated('zona_id');
        $count = 0;

        DB::transaction(function () use ($request, $dia, $zonaId, &$count): void {
            foreach ($request->validated('corporate_ids', []) as $corporateId) {
                $this->atribuir('corporate', (int) $corporateId, $dia, $zonaId);
                $count++;
            }

            foreach ($request->validated('woo_order_ids', []) as $wooOrderId) {
                $this->atribuir('b2c', (int) $wooOrderId, $dia, $zonaId);
                $count++;
            }
        });

        $nome = Zona::find($zonaId)?->nome ?? 'zona';

        return back()->with('status', $count === 1 ? "1 entrega passou para {$nome}." : "{$count} entregas passaram para {$nome}.");
    }

    /**
     * Cria ou muda a zona de uma entrega. Se mudar de zona, a posicao antiga
     * nao serve na volta nova: vai para o fim ate ser ordenada.
     */
    private function atribuir(string $tipo, int $id, string $dia, int $zonaId, bool $automatica = false): void
    {
        // Em semanas com feriado a empresa aparece noutro dia, mas a rota
        // dela continua a ser a do dia original (e e esse que a lista usa).
        if ($tipo === 'corporate') {
            $dia = Corporate::find($id)?->diaEntregaOriginalParaData($this->dataReferenciaParaDia($dia)) ?? $dia;
        }

        $atribuicao = AtribuicaoEntrega::firstOrNew([
            'tipo' => $tipo,
            'corporate_id' => $tipo === 'corporate' ? $id : null,
            'woo_order_id' => $tipo === 'b2c' ? $id : null,
            'dia_semana' => $dia,
        ]);

        if ($atribuicao->exists && (int) $atribuicao->zona_id === $zonaId) {
            // Confirmada a mao: deixa de mudar sozinha com os codigos postais.
            if (! $automatica && $atribuicao->zona_automatica) {
                $atribuicao->update(['zona_automatica' => false]);
            }

            return;
        }

        $atribuicao->zona_id = $zonaId;
        $atribuicao->zona_automatica = $automatica;
        $atribuicao->ordem = null;
        $atribuicao->save();
    }

    public function updateAtribuicao(StoreAtribuicaoEntregaRequest $request, AtribuicaoEntrega $atribuicao): RedirectResponse
    {
        // Mudou de zona ou de dia: a posicao antiga nao faz sentido na outra
        // volta, vai para o fim ate o admin a ordenar.
        $mudouRota = (int) $atribuicao->zona_id !== (int) $request->validated('zona_id')
            || $atribuicao->dia_semana !== $request->validated('dia_semana');

        $atribuicao->update([
            'ordem' => $mudouRota ? null : $atribuicao->ordem,
            'tipo' => $request->validated('tipo'),
            'corporate_id' => $request->validated('tipo') === 'corporate' ? $request->validated('corporate_id') : null,
            'woo_order_id' => $request->validated('tipo') === 'b2c' ? $request->validated('woo_order_id') : null,
            'zona_id' => $request->validated('zona_id'),
            'zona_automatica' => false,
            'dia_semana' => $request->validated('dia_semana'),
        ]);

        return back()->with('status', 'Entrega passou para '.($atribuicao->fresh()->zona?->nome ?? 'outra zona').'.');
    }

    public function destroyAtribuicao(AtribuicaoEntrega $atribuicao): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $atribuicao->delete();

        return back()->with('status', 'Atribuicao removida.');
    }

    /**
     * O admin define a ordem da volta de uma zona. Fica guardada na
     * atribuicao e vale para todas as semanas; as ordens que o colaborador
     * tenha mexido nas proximas voltas sao limpas para a nova aparecer logo.
     */
    private function paragensDaZona(\Illuminate\Support\Collection $atribuicoes): \Illuminate\Support\Collection
    {
        return $atribuicoes->map(function (AtribuicaoEntrega $atribuicao): array {
            $linha = $atribuicao->tipo === 'b2c'
                ? $this->linhaEntregaB2c($atribuicao->wooOrder, $atribuicao)
                : $this->linhaEntregaCorporate($atribuicao->corporate, $atribuicao);

            return $linha + ['atribuicao' => $atribuicao];
        })->values();
    }

    /**
     * As paragens no formato do organizador: id da atribuicao, codigo postal,
     * horario e onde fica a morada. Ao ver a pagina so se usam as moradas ja
     * localizadas; ao organizar procuram-se as que faltam.
     */
    private function paraOrganizar(\Illuminate\Support\Collection $paragens, bool $procurarMoradas = false): \Illuminate\Support\Collection
    {
        $geo = app(Geolocalizador::class);

        return $paragens->map(function (array $paragem) use ($geo, $procurarMoradas): array {
            $cp = $paragem['cp'] ?: $this->codigoPostalNaMorada($paragem['morada']);
            $coord = $geo->coordenadas($paragem['morada'] ?? null, $cp, $paragem['localidade'] ?? null, $procurarMoradas);

            return [
                'chave' => $paragem['atribuicao']->id,
                'cp' => $cp,
                'horario' => $paragem['horario'] ?? null,
                'lat' => $coord[0] ?? null,
                'lng' => $coord[1] ?? null,
            ];
        })->values();
    }

    /**
     * Organiza as voltas do dia (todas, ou so a de uma zona): primeiro o que
     * tem de ser entregue cedo, depois pela proximidade, dentro dos horarios.
     */
    public function organizarVoltas(Request $request, OrganizadorDeVoltas $organizador): RedirectResponse
    {
        $data = $request->validate([
            'dia_semana' => ['required', 'in:'.implode(',', self::DIAS)],
            'zona_id' => ['nullable', 'integer', 'exists:zonas,id'],
            'semana' => ['nullable', 'integer', 'min:0', 'max:12'],
        ]);

        $dataDia = $this->dataReferenciaParaDia($data['dia_semana']);
        [$atribuicoes] = $this->entregasDoDiaParaRotas($data['dia_semana'], $dataDia);
        $porZona = $atribuicoes->whereNotNull('zona_id')
            ->when(filled($data['zona_id'] ?? null), fn ($colecao) => $colecao->where('zona_id', (int) $data['zona_id']))
            ->groupBy('zona_id');

        $resumo = [];

        DB::transaction(function () use ($porZona, $organizador, $dataDia, &$resumo): void {
            foreach ($porZona as $atribuicoesDaZona) {
                $zona = $atribuicoesDaZona->first()->zona;
                $seguinte = 1;

                // Uma volta por pessoa (em semanas com feriado a mesma zona pode
                // ter as entregas de dois dias, feitas por pessoas diferentes).
                foreach ($atribuicoesDaZona->groupBy(fn (AtribuicaoEntrega $a): int => $this->quemFaz($a, $dataDia)) as $grupo) {
                    $ordem = $organizador->organizar($this->paraOrganizar($this->paragensDaZona($grupo), procurarMoradas: true), $zona?->partida_cp);
                    $porId = $grupo->keyBy('id');

                    foreach ($ordem as $paragem) {
                        $porId->get($paragem['chave'])?->update(['ordem' => $seguinte++]);
                    }

                    $resumo[] = $zona?->nome.' sai '.($ordem->first()['saida'] ?? '?')
                        .($ordem->where('atrasada', true)->isNotEmpty() ? ' ('.$ordem->where('atrasada', true)->count().' fora de horas)' : '');
                }

                $this->limparOrdemDosRegistos($atribuicoesDaZona);
            }
        });

        return back()->with('status', $resumo === []
            ? 'Nao ha voltas para organizar neste dia.'
            : 'Voltas organizadas por horario e distancia: '.implode('; ', $resumo).'.');
    }

    /** Quem faz esta entrega nesta data (0 = ninguem). */
    private function quemFaz(AtribuicaoEntrega $atribuicao, Carbon $data): int
    {
        return (int) ($atribuicao->zona?->colaboradorIdEm($data, $atribuicao->dia_semana) ?? 0);
    }

    /** As entregas de hoje para a frente passam a seguir a ordem da volta. */
    private function limparOrdemDosRegistos(\Illuminate\Support\Collection $atribuicoes): void
    {
        $corporateIds = $atribuicoes->where('tipo', 'corporate')->pluck('corporate_id')->filter()->values();
        $wooOrderIds = $atribuicoes->where('tipo', 'b2c')->pluck('woo_order_id')->filter()->values();

        if ($corporateIds->isEmpty() && $wooOrderIds->isEmpty()) {
            return;
        }

        RegistoEntrega::query()
            ->whereDate('data_entrega', '>=', now()->toDateString())
            ->whereNotNull('ordem')
            ->where(function ($query) use ($corporateIds, $wooOrderIds): void {
                $query->where(fn ($query) => $query->where('tipo', 'corporate')->whereIn('corporate_id', $corporateIds))
                    ->orWhere(fn ($query) => $query->where('tipo', 'b2c')->whereIn('woo_order_id', $wooOrderIds));
            })
            ->update(['ordem' => null]);
    }

    public function updateOrdemRota(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'zona_id' => ['required', 'integer', 'exists:zonas,id'],
            'ordens' => ['required', 'array'],
            'ordens.*' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        // Pelos ids e pela zona: em semanas com feriado ha entregas desta volta
        // que estao gravadas com o dia original.
        $atribuicoes = AtribuicaoEntrega::query()
            ->whereIn('id', collect($data['ordens'])->keys()->map(fn ($id): int => (int) $id))
            ->where('zona_id', (int) $data['zona_id'])
            ->get()
            ->keyBy('id');

        DB::transaction(function () use ($data, $atribuicoes): void {
            foreach ($data['ordens'] as $id => $ordem) {
                $atribuicoes->get((int) $id)?->update([
                    'ordem' => filled($ordem) ? (int) $ordem : null,
                ]);
            }

            $this->limparOrdemDosRegistos($atribuicoes);
        });

        return back()->with('status', 'Ordem da volta guardada.');
    }

    private function nomeAtribuicao(AtribuicaoEntrega $atribuicao): string
    {
        return $atribuicao->tipo === 'b2c'
            ? ($atribuicao->wooOrder?->billing_name ?? '')
            : trim(($atribuicao->corporate?->empresa ?? '').' '.($atribuicao->corporate?->sucursal ?? ''));
    }

    public function minhasEntregas(EntregasDoDia $entregasDoDia): View
    {
        $dataSelecionada = filled(request('data'))
            ? Carbon::parse(request('data'))->startOfDay()
            : now()->startOfDay();
        $dia = self::DIAS[$dataSelecionada->dayOfWeek] ?? null;
        $data = $dataSelecionada->toDateString();
        $q = request('q', '');
        $status = request('status', '');
        $termo = mb_strtolower(trim($q));

        // As entregas das zonas que calham a este colaborador neste dia (pelo
        // horario da zona ou por substituicao), ja pela ordem da volta.
        $atribuicoes = $entregasDoDia->doColaborador(auth()->user(), $dataSelecionada)
            ->filter(fn (AtribuicaoEntrega $atribuicao): bool => $termo === '' || str_contains(mb_strtolower(implode(' ', array_filter([
                $atribuicao->corporate?->empresa,
                $atribuicao->corporate?->sucursal,
                $atribuicao->corporate?->morada_entrega,
                $atribuicao->corporate?->fatura_morada,
                $atribuicao->wooOrder?->billing_name,
                $atribuicao->wooOrder?->billing_phone,
                $atribuicao->wooOrder?->billing_email,
                $atribuicao->wooOrder?->woo_id,
            ]))), $termo))
            ->values();

        $registos = $atribuicoes
            ->map(fn (AtribuicaoEntrega $atribuicao): RegistoEntrega => $this->registoPara($atribuicao, $data, (int) auth()->id()))
            ->values();
        $registos = (new \Illuminate\Database\Eloquent\Collection($registos->all()))->load(['corporate', 'wooOrder']);

        // Progresso da volta inteira (antes de filtrar por estado) e a
        // primeira paragem ainda por fazer, para o botao "Continuar volta".
        $total = $registos->count();
        $feitas = $registos->whereIn('status', ['entregue', 'falhou'])->count();
        $proxima = $registos->first(fn (RegistoEntrega $registo): bool => $this->estaPendente($registo));

        $registos = $registos
            ->when(in_array($status, ['pendente', 'entregue', 'falhou'], true), fn ($collection) => $collection->filter(
                fn (RegistoEntrega $registo): bool => $status === 'pendente' ? $this->estaPendente($registo) : $registo->status === $status
            )->values())
            ->values();

        return view('entregas.minhas', compact('registos', 'q', 'status', 'data', 'dia', 'total', 'feitas', 'proxima'));
    }

    private function estaPendente(RegistoEntrega $registo): bool
    {
        return ! in_array($registo->status, ['entregue', 'falhou'], true);
    }

    /**
     * Os registos da volta de quem faz esta entrega, pela ordem da volta.
     *
     * @return \Illuminate\Support\Collection<int, RegistoEntrega>
     */
    private function voltaDe(RegistoEntrega $registoEntrega, EntregasDoDia $entregasDoDia): \Illuminate\Support\Collection
    {
        $colaborador = $registoEntrega->user ?? auth()->user();
        $data = $registoEntrega->data_entrega->copy()->startOfDay();

        return $entregasDoDia->doColaborador($colaborador, $data)
            ->map(fn (AtribuicaoEntrega $atribuicao): RegistoEntrega => $this->registoPara($atribuicao, $data->toDateString(), (int) $colaborador->id))
            ->values();
    }

    /** A proxima paragem por fazer depois desta (ou, se nao houver, a primeira que ficou para tras). */
    private function proximaPendente(\Illuminate\Support\Collection $volta, RegistoEntrega $atual): ?RegistoEntrega
    {
        $posicao = $volta->search(fn (RegistoEntrega $registo): bool => $registo->id === $atual->id);
        $depois = $posicao === false ? $volta : $volta->slice($posicao + 1);
        $antes = $posicao === false ? collect() : $volta->slice(0, $posicao);

        return $depois->first(fn (RegistoEntrega $registo): bool => $this->estaPendente($registo))
            ?? $antes->first(fn (RegistoEntrega $registo): bool => $this->estaPendente($registo));
    }

    /**
     * O registo de entrega desta atribuicao nesse dia. Quem entrega e quem faz
     * a zona nesse dia: se a zona mudou de pessoa (ferias, falta) e a entrega
     * ainda nao foi feita, o registo passa para quem a vai fazer.
     */
    private function registoPara(AtribuicaoEntrega $atribuicao, string $data, ?int $userId): RegistoEntrega
    {
        $registos = RegistoEntrega::query()
            ->where('tipo', $atribuicao->tipo)
            ->when($atribuicao->tipo === 'b2c',
                fn ($query) => $query->where('woo_order_id', $atribuicao->woo_order_id),
                fn ($query) => $query->where('corporate_id', $atribuicao->corporate_id))
            ->whereDate('data_entrega', $data)
            ->get();

        $registo = $registos->firstWhere('user_id', $userId) ?? $registos->first();

        if ($registo === null) {
            return RegistoEntrega::create([
                'tipo' => $atribuicao->tipo,
                'corporate_id' => $atribuicao->tipo === 'corporate' ? $atribuicao->corporate_id : null,
                'woo_order_id' => $atribuicao->tipo === 'b2c' ? $atribuicao->woo_order_id : null,
                'user_id' => $userId,
                'data_entrega' => $data,
            ]);
        }

        if ($userId !== null && (int) $registo->user_id !== $userId && in_array($registo->status, [null, 'pendente'], true)) {
            $registo->update(['user_id' => $userId]);
        }

        return $registo;
    }

    public function show(RegistoEntrega $registoEntrega, EntregasDoDia $entregasDoDia): View
    {
        abort_unless(auth()->user()->isAdmin() || $registoEntrega->user_id === auth()->id(), 403);

        $registoEntrega->load(['corporate', 'wooOrder', 'user']);

        $volta = $this->voltaDe($registoEntrega, $entregasDoDia);
        $posicao = $volta->search(fn (RegistoEntrega $registo): bool => $registo->id === $registoEntrega->id);
        $navegacao = [
            'posicao' => $posicao === false ? null : $posicao + 1,
            'total' => $volta->count(),
            'feitas' => $volta->whereIn('status', ['entregue', 'falhou'])->count(),
            'anterior' => $posicao === false || $posicao === 0 ? null : $volta[$posicao - 1],
            'seguinte' => $posicao === false ? null : $volta->get($posicao + 1),
        ];

        return view('entregas.show', compact('registoEntrega', 'navegacao'));
    }

    public function update(UpdateRegistoEntregaRequest $request, RegistoEntrega $registoEntrega, EntregasDoDia $entregasDoDia): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin() || $registoEntrega->user_id === auth()->id(), 403);

        $fotos = $registoEntrega->fotos ?? [];

        foreach ($request->file('fotos', []) as $foto) {
            if (count($fotos) >= 6) {
                break;
            }

            $directory = "entregas/{$registoEntrega->data_entrega->format('Y/m')}/{$registoEntrega->id}";

            try {
                $path = $this->storeDeliveryPhoto($foto, $directory);
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'fotos' => 'Nao foi possivel guardar a foto. Confirma que e uma imagem valida e que o servidor tem permissao de escrita no storage.',
                ]);
            }

            if ($path !== false) {
                $fotos[] = $this->relativePublicDiskPath($path);
            } else {
                throw ValidationException::withMessages([
                    'fotos' => 'Nao foi possivel guardar a foto. Confirma que e uma imagem valida e que o servidor tem permissao de escrita no storage.',
                ]);
            }
        }

        $status = $request->validated('status');
        $jaEstavaEntregue = $registoEntrega->status === 'entregue' && $registoEntrega->hora_entrega !== null;

        $registoEntrega->update([
            'status' => $status,
            'nota' => $request->validated('nota'),
            'hora_entrega' => $status === 'entregue'
                ? ($jaEstavaEntregue ? $registoEntrega->hora_entrega->format('H:i:s') : now()->format('H:i:s'))
                : null,
            'fotos' => $fotos,
        ]);

        // Marcou entregue / nao entregue: segue logo para a proxima paragem
        // por fazer, sem ter de voltar a lista.
        if (in_array($request->input('acao'), ['entregue', 'falhou'], true)) {
            $nome = $registoEntrega->tipo === 'b2c'
                ? ($registoEntrega->wooOrder?->billing_name ?: 'Cliente B2C')
                : trim(($registoEntrega->corporate?->empresa ?? '').' '.($registoEntrega->corporate?->sucursal ?? ''));
            $feito = ($status === 'entregue' ? 'Entregue: ' : 'Nao entregue: ').$nome;

            $proxima = $this->proximaPendente($this->voltaDe($registoEntrega->fresh(['user']), $entregasDoDia), $registoEntrega);

            if ($proxima !== null) {
                return redirect()->route('minhas-entregas.show', $proxima)->with('status', $feito.'. Proxima paragem.');
            }

            return redirect()
                ->route('minhas-entregas.index', ['data' => $registoEntrega->data_entrega->toDateString()])
                ->with('status', $feito.'. Volta terminada, nao ha mais entregas por fazer.');
        }

        return redirect()->route('minhas-entregas.show', $registoEntrega)->with('status', 'Entrega atualizada.');
    }

    public function destroyFoto(RegistoEntrega $registoEntrega, int $index): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin() || $registoEntrega->user_id === auth()->id(), 403);

        $path = DB::transaction(function () use ($registoEntrega, $index): string {
            $fotos = $registoEntrega->fresh()->fotos ?? [];

            abort_unless(array_key_exists($index, $fotos), 404);

            $path = (string) $fotos[$index];
            unset($fotos[$index]);

            $registoEntrega->forceFill(['fotos' => array_values($fotos)])->save();

            return $path;
        });

        Storage::disk('public')->delete($this->relativePublicDiskPath($path));

        return redirect()->route('minhas-entregas.show', $registoEntrega)->with('status', 'Foto removida.');
    }

    private function storeDeliveryPhoto(UploadedFile $foto, string $directory): string|false
    {
        if ($this->isHeicOrHeifUpload($foto)) {
            $convertedPath = $this->convertHeicToJpeg($foto, $directory);

            if ($convertedPath !== null) {
                return $convertedPath;
            }

            return Storage::disk('public')->putFileAs(
                $directory,
                $foto,
                Str::random(40).'.'.$this->heicExtension($foto)
            );
        }

        return $foto->storePublicly($directory, 'public');
    }

    private function convertHeicToJpeg(UploadedFile $foto, string $directory): ?string
    {
        $path = $directory.'/'.Str::random(40).'.jpg';

        try {
            if (class_exists(\Imagick::class)) {
                $image = new \Imagick($foto->getRealPath());
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality(85);

                if (Storage::disk('public')->put($path, $image->getImagesBlob(), 'public')) {
                    $image->clear();
                    $image->destroy();

                    return $path;
                }

                $image->clear();
                $image->destroy();
            }
        } catch (Throwable) {
            //
        }

        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        $contents = @file_get_contents($foto->getRealPath());

        if ($contents === false) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            return null;
        }

        ob_start();
        $written = imagejpeg($image, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        if (! $written || $jpeg === false) {
            return null;
        }

        return Storage::disk('public')->put($path, $jpeg, 'public') ? $path : null;
    }

    private function isHeicOrHeifUpload(UploadedFile $foto): bool
    {
        $mime = (string) $foto->getMimeType();

        if (in_array($mime, ['image/heic', 'image/heif'], true)) {
            return true;
        }

        $extension = strtolower($foto->getClientOriginalExtension());

        if (in_array($extension, ['heic', 'heif'], true) && $this->hasHeicOrHeifSignature($foto)) {
            return true;
        }

        return $this->hasHeicOrHeifSignature($foto);
    }

    private function hasHeicOrHeifSignature(UploadedFile $foto): bool
    {
        $handle = @fopen($foto->getRealPath(), 'rb');

        if ($handle === false) {
            return false;
        }

        $header = fread($handle, 64);
        fclose($handle);

        if ($header === false || substr($header, 4, 4) !== 'ftyp') {
            return false;
        }

        foreach (['heic', 'heix', 'hevc', 'hevx', 'heif', 'mif1', 'msf1'] as $brand) {
            if (str_contains($header, $brand)) {
                return true;
            }
        }

        return false;
    }

    private function heicExtension(UploadedFile $foto): string
    {
        $extension = strtolower($foto->getClientOriginalExtension());

        return $extension === 'heif' ? 'heif' : 'heic';
    }

    private function relativePublicDiskPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $publicRoot = str_replace('\\', '/', storage_path('app/public')).'/';

        if (str_starts_with($path, $publicRoot)) {
            return ltrim(substr($path, strlen($publicRoot)), '/');
        }

        if (str_starts_with($path, 'public/')) {
            return substr($path, strlen('public/'));
        }

        return ltrim($path, '/');
    }

    private function b2cOrdersParaDia(string $dia, Carbon $dataSelecionada, string $q = ''): \Illuminate\Support\Collection
    {
        return app(EntregasDoDia::class)->encomendasB2c($dia, $dataSelecionada, $q);
    }

    private function dataReferenciaParaDia(string $dia): Carbon
    {
        $dayOfWeek = array_search($dia, self::DIAS, true);
        $data = now()->startOfDay();

        if ($dayOfWeek === false) {
            return $data;
        }

        while ($data->dayOfWeek !== $dayOfWeek) {
            $data->addDay();
        }

        // Ver/organizar as voltas de uma semana mais a frente (ex.: quando
        // esta semana tem feriado e as voltas estao trocadas).
        return $data->addWeeks($this->semanaPedida());
    }

    private function semanaPedida(): int
    {
        return max(0, min(12, (int) request('semana', 0)));
    }
}
