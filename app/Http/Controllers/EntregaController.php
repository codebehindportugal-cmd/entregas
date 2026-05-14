<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkAtribuicaoEntregaRequest;
use App\Http\Requests\StoreAtribuicaoEntregaRequest;
use App\Http\Requests\UpdateRegistoEntregaRequest;
use App\Models\AtribuicaoEntrega;
use App\Models\Corporate;
use App\Models\PreparacaoItem;
use App\Models\RegistoEntrega;
use App\Models\User;
use App\Models\WooOrder;
use App\Services\ComprasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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

    public function index(): View
    {
        $dia = request('dia', self::DIAS[now()->dayOfWeek] ?? 'Segunda');
        $q = request('q', '');
        $userId = (int) request('user_id', 0);
        $dataB2c = $this->dataReferenciaParaDia($dia);

        $corporatesDoDia = Corporate::where('ativo', true)
            ->whereJsonContains('dias_entrega', $dia)
            ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                $query->where('empresa', 'like', "%{$q}%")
                    ->orWhere('sucursal', 'like', "%{$q}%")
                    ->orWhere('morada_entrega', 'like', "%{$q}%")
                    ->orWhere('fatura_morada', 'like', "%{$q}%");
            }))
            ->orderBy('empresa')
            ->get();

        $b2cOrders = $this->b2cOrdersParaDia($dia, $dataB2c, $q)->get();

        return view('entregas.index', [
            'dia' => $dia,
            'q' => $q,
            'userId' => $userId,
            'dias' => array_values(self::DIAS),
            'atribuicoes' => AtribuicaoEntrega::with(['corporate', 'wooOrder', 'user'])
                ->where('dia_semana', $dia)
                ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
                ->where(function ($query) use ($dia, $q): void {
                    $query->whereHas('corporate', fn ($query) => $query
                        ->whereJsonContains('dias_entrega', $dia)
                        ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                            $query->where('empresa', 'like', "%{$q}%")
                                ->orWhere('sucursal', 'like', "%{$q}%")
                                ->orWhere('morada_entrega', 'like', "%{$q}%")
                                ->orWhere('fatura_morada', 'like', "%{$q}%");
                        }))
                    )->orWhereHas('wooOrder', fn ($query) => $query->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                        $query->where('billing_name', 'like', "%{$q}%")
                            ->orWhere('billing_phone', 'like', "%{$q}%")
                            ->orWhere('billing_email', 'like', "%{$q}%")
                            ->orWhere('woo_id', 'like', "%{$q}%");
                    })));
                })
                ->orderBy('tipo')
                ->orderBy('corporate_id')
                ->orderBy('woo_order_id')
                ->get(),
            'corporates' => $corporatesDoDia,
            'b2cOrders' => $b2cOrders,
            'dataB2c' => $dataB2c->toDateString(),
            'colaboradores' => User::where('ativo', true)->orderBy('name')->get(),
        ]);
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
            $corporateIdsComEntrega = Corporate::where('ativo', true)
                ->whereJsonContains('dias_entrega', $dia)
                ->get()
                ->filter(fn (Corporate $corporate) => $corporate->temEntregaNaData($dataSelecionada))
                ->pluck('id');

            AtribuicaoEntrega::with('corporate')
                ->where('dia_semana', $dia)
                ->where('tipo', 'corporate')
                ->whereIn('corporate_id', $corporateIdsComEntrega)
                ->whereHas('corporate', fn ($query) => $query
                    ->where('ativo', true)
                    ->whereJsonContains('dias_entrega', $dia)
                )
                ->get()
                ->each(function (AtribuicaoEntrega $atribuicao) use ($data): void {
                    RegistoEntrega::firstOrCreate([
                        'tipo' => 'corporate',
                        'corporate_id' => $atribuicao->corporate_id,
                        'user_id' => $atribuicao->user_id,
                        'data_entrega' => $data,
                    ]);
                });

            $b2cOrderIdsComEntrega = $this->b2cOrdersParaDia($dia, $dataSelecionada)->pluck('id');

            AtribuicaoEntrega::with('wooOrder')
                ->where('dia_semana', $dia)
                ->where('tipo', 'b2c')
                ->whereIn('woo_order_id', $b2cOrderIdsComEntrega)
                ->get()
                ->each(fn (AtribuicaoEntrega $atribuicao) => $this->firstOrCreateRegistoB2c($atribuicao, $data));
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
        $dataSelecionada = filled($request->input('data'))
            ? Carbon::parse($request->input('data'))
            : now();

        $data = $dataSelecionada->toDateString();
        $dia = self::DIAS[$dataSelecionada->dayOfWeek] ?? 'Segunda';

        if (filled($request->input('dia')) && in_array($request->input('dia'), self::DIAS, true)) {
            $dia = $request->input('dia');
        }

        $q = $request->string('q')->toString();

        $corporates = Corporate::where('ativo', true)
            ->whereJsonContains('dias_entrega', $dia)
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

        $b2cOrders = $this->b2cOrdersParaDia($dia, $dataSelecionada, $q)->get();

        $corporates->each(function (Corporate $corporate) use ($data): void {
            PreparacaoItem::firstOrCreate([
                'data_preparacao' => $data,
                'tipo' => 'corporate',
                'corporate_id' => $corporate->id,
            ]);
        });

        $b2cOrders->each(function (WooOrder $order) use ($data): void {
            PreparacaoItem::firstOrCreate([
                'data_preparacao' => $data,
                'tipo' => 'b2c',
                'woo_order_id' => $order->id,
            ]);
        });

        $preparacaoItems = PreparacaoItem::with(['corporate', 'wooOrder', 'feitoPor'])
            ->whereDate('data_preparacao', $data)
            ->where(function ($query) use ($corporates, $b2cOrders): void {
                $query->whereIn('corporate_id', $corporates->pluck('id'))
                    ->orWhereIn('woo_order_id', $b2cOrders->pluck('id'));
            })
            ->get()
            ->keyBy(fn (PreparacaoItem $item) => $item->tipo.'-'.($item->corporate_id ?: $item->woo_order_id));

        $produtosKg = ComprasService::PRODUTOS_KG;
        $totaisFrutas = collect(ComprasService::FRUTAS)
            ->mapWithKeys(fn (string $label, string $fruta) => [
                $fruta => $corporates->sum(fn (Corporate $corporate) => in_array($fruta, $produtosKg, true)
                    ? (float) ($corporate->frutasParaDia($dia)[$fruta] ?? 0)
                    : (int) ($corporate->frutasParaDia($dia)[$fruta] ?? 0)),
            ])
            ->all();
        $totalPecas = collect($totaisFrutas)
            ->except($produtosKg)
            ->sum(fn (int|float $quantidade): int => (int) $quantidade);

        return view('entregas.preparacao', [
            'data' => $data,
            'dia' => $dia,
            'q' => $q,
            'dias' => array_values(self::DIAS),
            'corporates' => $corporates,
            'b2cOrders' => $b2cOrders,
            'preparacaoItems' => $preparacaoItems,
            'totalCaixas' => $corporates->sum('numero_caixas'),
            'totalPecas' => $totalPecas,
            'totaisFrutas' => $totaisFrutas,
            'totalFeitos' => $preparacaoItems->where('feito', true)->count(),
            'totalPorFazer' => $preparacaoItems->where('feito', false)->count(),
        ]);
    }

    public function updatePreparacaoItem(Request $request, PreparacaoItem $item): RedirectResponse
    {
        $feito = $request->boolean('feito');

        $item->update([
            'feito' => $feito,
            'feito_at' => $feito ? now() : null,
            'feito_por' => $feito ? auth()->id() : null,
        ]);

        return back()->with('status', $feito ? 'Preparacao marcada como feita.' : 'Preparacao marcada como por fazer.');
    }

    public function updatePreparacaoProdutos(Request $request, PreparacaoItem $item): RedirectResponse
    {
        abort_unless($item->tipo === 'b2c', 404);

        $data = $request->validate([
            'produtos_picados' => ['nullable', 'array'],
            'produtos_picados.*' => ['string'],
        ]);

        $picados = array_values(array_unique($data['produtos_picados'] ?? []));
        $totalProdutos = count($item->wooOrder?->line_items ?? []);
        $feito = $totalProdutos > 0 && count($picados) >= $totalProdutos;

        $item->update([
            'produtos_picados' => $picados,
            'feito' => $feito,
            'feito_at' => $feito ? now() : null,
            'feito_por' => $feito ? auth()->id() : null,
        ]);

        return back()->with('status', $feito ? 'Encomenda B2C preparada.' : 'Produtos picados guardados.');
    }

    public function storeAtribuicao(StoreAtribuicaoEntregaRequest $request): RedirectResponse
    {
        AtribuicaoEntrega::updateOrCreate(
            [
                'tipo' => $request->validated('tipo'),
                'corporate_id' => $request->validated('tipo') === 'corporate' ? $request->validated('corporate_id') : null,
                'woo_order_id' => $request->validated('tipo') === 'b2c' ? $request->validated('woo_order_id') : null,
                'dia_semana' => $request->validated('dia_semana'),
            ],
            ['user_id' => $request->validated('user_id')]
        );

        return back()->with('status', 'Atribuicao guardada.');
    }

    public function storeAtribuicoesBulk(BulkAtribuicaoEntregaRequest $request): RedirectResponse
    {
        $count = 0;

        foreach ($request->validated('corporate_ids', []) as $corporateId) {
            AtribuicaoEntrega::updateOrCreate(
                [
                    'tipo' => 'corporate',
                    'corporate_id' => $corporateId,
                    'woo_order_id' => null,
                    'dia_semana' => $request->validated('dia_semana'),
                ],
                ['user_id' => $request->validated('user_id')]
            );

            $count++;
        }

        foreach ($request->validated('woo_order_ids', []) as $wooOrderId) {
            AtribuicaoEntrega::updateOrCreate(
                [
                    'tipo' => 'b2c',
                    'corporate_id' => null,
                    'woo_order_id' => $wooOrderId,
                    'dia_semana' => $request->validated('dia_semana'),
                ],
                ['user_id' => $request->validated('user_id')]
            );

            $count++;
        }

        return back()->with('status', "{$count} atribuicoes guardadas.");
    }

    public function updateAtribuicao(StoreAtribuicaoEntregaRequest $request, AtribuicaoEntrega $atribuicao): RedirectResponse
    {
        $atribuicao->update([
            'tipo' => $request->validated('tipo'),
            'corporate_id' => $request->validated('tipo') === 'corporate' ? $request->validated('corporate_id') : null,
            'woo_order_id' => $request->validated('tipo') === 'b2c' ? $request->validated('woo_order_id') : null,
            'user_id' => $request->validated('user_id'),
            'dia_semana' => $request->validated('dia_semana'),
        ]);

        return back()->with('status', 'Atribuicao atualizada.');
    }

    public function destroyAtribuicao(AtribuicaoEntrega $atribuicao): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $atribuicao->delete();

        return back()->with('status', 'Atribuicao removida.');
    }

    public function minhasEntregas(): View
    {
        $dataSelecionada = filled(request('data'))
            ? Carbon::parse(request('data'))->startOfDay()
            : now()->startOfDay();
        $dia = self::DIAS[$dataSelecionada->dayOfWeek] ?? null;
        $data = $dataSelecionada->toDateString();
        $q = request('q', '');
        $status = request('status', '');

        $atribuicoes = AtribuicaoEntrega::with(['corporate', 'wooOrder'])
            ->where('user_id', auth()->id())
            ->when($dia, fn ($query) => $query->where('dia_semana', $dia))
            ->where(function ($query) use ($q): void {
                $query->whereHas('corporate', fn ($query) => $query->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                    $query->where('empresa', 'like', "%{$q}%")
                        ->orWhere('sucursal', 'like', "%{$q}%")
                        ->orWhere('morada_entrega', 'like', "%{$q}%")
                        ->orWhere('fatura_morada', 'like', "%{$q}%");
                })))->orWhereHas('wooOrder', fn ($query) => $query->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                    $query->where('billing_name', 'like', "%{$q}%")
                        ->orWhere('billing_phone', 'like', "%{$q}%")
                        ->orWhere('billing_email', 'like', "%{$q}%")
                        ->orWhere('woo_id', 'like', "%{$q}%");
                })));
            })
            ->get();
        $atribuicoes = $atribuicoes
            ->filter(fn (AtribuicaoEntrega $atribuicao) => $atribuicao->tipo === 'b2c' || $atribuicao->corporate?->temEntregaNaData($dataSelecionada))
            ->values();

        $registos = $atribuicoes->map(function (AtribuicaoEntrega $atribuicao) use ($data) {
            if ($atribuicao->tipo === 'b2c') {
                return $this->firstOrCreateRegistoB2c($atribuicao, $data);
            }

            return RegistoEntrega::firstOrCreate([
                'tipo' => 'corporate',
                'corporate_id' => $atribuicao->corporate_id,
                'user_id' => $atribuicao->user_id,
                'data_entrega' => $data,
            ]);
        })->load(['corporate', 'wooOrder'])
            ->when(in_array($status, ['pendente', 'entregue', 'falhou'], true), fn ($collection) => $collection->where('status', $status)->values())
            ->sortBy([
                fn (RegistoEntrega $registo) => $registo->ordem ?? 999999,
                fn (RegistoEntrega $registo) => $registo->tipo === 'b2c'
                    ? ($registo->wooOrder?->billing_name ?? '')
                    : ($registo->corporate?->empresa ?? ''),
            ])
            ->values();

        return view('entregas.minhas', compact('registos', 'q', 'status', 'data', 'dia'));
    }

    public function updateOrdemMinhasEntregas(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'data' => ['required', 'date'],
            'ordens' => ['nullable', 'array'],
            'ordens.*' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);
        $dataEntrega = Carbon::parse($data['data'])->toDateString();

        $ids = collect($data['ordens'] ?? [])
            ->keys()
            ->map(fn (int|string $id): int => (int) $id)
            ->filter()
            ->values();

        if ($ids->isEmpty()) {
            return back()->with('status', 'Ordem da volta guardada.');
        }

        $registos = RegistoEntrega::query()
            ->whereIn('id', $ids)
            ->where('user_id', auth()->id())
            ->whereDate('data_entrega', $dataEntrega)
            ->get()
            ->keyBy('id');

        foreach ($data['ordens'] ?? [] as $id => $ordem) {
            $registo = $registos->get((int) $id);

            if ($registo === null) {
                continue;
            }

            $registo->update([
                'ordem' => filled($ordem) ? (int) $ordem : null,
            ]);
        }

        return redirect()->route('minhas-entregas.index', ['data' => $dataEntrega])->with('status', 'Ordem da volta guardada.');
    }

    public function show(RegistoEntrega $registoEntrega): View
    {
        abort_unless(auth()->user()->isAdmin() || $registoEntrega->user_id === auth()->id(), 403);

        $registoEntrega->load(['corporate', 'wooOrder', 'user']);

        return view('entregas.show', compact('registoEntrega'));
    }

    public function update(UpdateRegistoEntregaRequest $request, RegistoEntrega $registoEntrega): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin() || $registoEntrega->user_id === auth()->id(), 403);

        $fotos = $registoEntrega->fotos ?? [];

        foreach ($request->file('fotos', []) as $foto) {
            if (count($fotos) >= 6) {
                break;
            }

            // Sem Intervention Image instalado ainda; o upload fica pronto para compressao futura.
            $path = $foto->storePublicly(
                "entregas/{$registoEntrega->data_entrega->format('Y/m')}/{$registoEntrega->id}",
                'public'
            );

            if ($path !== false) {
                $fotos[] = $path;
            } else {
                throw ValidationException::withMessages([
                    'fotos' => 'Nao foi possivel guardar a foto. Tente novamente ou escolha uma imagem menor.',
                ]);
            }
        }

        $registoEntrega->update([
            'status' => $request->validated('status'),
            'nota' => $request->validated('nota'),
            'hora_entrega' => $request->validated('status') === 'entregue' ? now()->format('H:i:s') : null,
            'fotos' => $fotos,
        ]);

        return redirect()->route('minhas-entregas.show', $registoEntrega)->with('status', 'Entrega atualizada.');
    }

    private function b2cOrdersParaDia(string $dia, Carbon $dataSelecionada, string $q = '')
    {
        $data = $dataSelecionada->toDateString();
        $diaB2c = match ($dia) {
            'Segunda' => 'segunda',
            'Quarta' => 'quarta',
            'Sabado' => 'sabado',
            default => null,
        };

        return WooOrder::query()
            ->where(function ($query): void {
                $query->whereIn('status', ['processing', 'on-hold', 'pending'])
                    ->orWhereIn('status', ['subscricao', 'wc-subscricao', 'active'])
                    ->orWhere('source_type', 'subscription');
            })
            ->when($diaB2c !== null, fn ($query) => $query->where(function ($query) use ($diaB2c, $data): void {
                $query->whereDate('postponed_until', $data)
                    ->orWhere(function ($query) use ($diaB2c, $data): void {
                        $query->where(function ($query) use ($data): void {
                            $query->whereNull('postponed_until')
                                ->orWhereDate('postponed_until', '<', $data);
                        })->where(function ($query) use ($diaB2c, $data): void {
                            $query->whereJsonContains('delivery_dates', $data)
                                ->orWhereDate('scheduled_delivery_at', $data)
                                ->orWhere(function ($query) use ($diaB2c, $data): void {
                                    $query->where('source_type', 'order')
                                        ->where('status', '!=', 'subscricao')
                                        ->where('dia_entrega', $diaB2c)
                                        ->where(function ($query) use ($data): void {
                                            $query->whereNull('scheduled_delivery_at')
                                                ->orWhereDate('scheduled_delivery_at', '<=', $data)
                                                ->orWhereDate('first_delivery_at', '<=', $data);
                                        });
                                })
                                ->orWhere(function ($query) use ($diaB2c): void {
                                    $query->where(function ($query): void {
                                        $query->whereNull('delivery_dates')
                                            ->orWhereJsonLength('delivery_dates', 0);
                                    })->whereNull('scheduled_delivery_at')
                                        ->where(function ($query) use ($diaB2c): void {
                                            $query->where('dia_entrega', $diaB2c)
                                                ->orWhereNull('dia_entrega');
                                        });
                                });
                        });
                    });
            }), fn ($query) => $query->whereRaw('1 = 0'))
            ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                $query->where('billing_name', 'like', "%{$q}%")
                    ->orWhere('billing_phone', 'like', "%{$q}%")
                    ->orWhere('billing_email', 'like', "%{$q}%")
                    ->orWhere('woo_id', 'like', "%{$q}%");
            }))
            ->orderBy('billing_name');
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

        return $data;
    }

    private function firstOrCreateRegistoB2c(AtribuicaoEntrega $atribuicao, string $data): RegistoEntrega
    {
        return RegistoEntrega::firstOrCreate([
            'tipo' => 'b2c',
            'woo_order_id' => $atribuicao->woo_order_id,
            'user_id' => $atribuicao->user_id,
            'data_entrega' => $data,
        ], [
            'corporate_id' => null,
        ]);
    }
}
