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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EntregaController extends Controller
{
    private const DIAS = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
    ];

    public function index(): View
    {
        $dia = request('dia', self::DIAS[now()->dayOfWeek] ?? 'Segunda');
        $q = request('q', '');
        $userId = (int) request('user_id', 0);

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

        return view('entregas.index', [
            'dia' => $dia,
            'q' => $q,
            'userId' => $userId,
            'dias' => array_values(self::DIAS),
            'atribuicoes' => AtribuicaoEntrega::with(['corporate', 'user'])
                ->where('dia_semana', $dia)
                ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
                ->whereHas('corporate', fn ($query) => $query
                    ->whereJsonContains('dias_entrega', $dia)
                    ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                        $query->where('empresa', 'like', "%{$q}%")
                            ->orWhere('sucursal', 'like', "%{$q}%")
                            ->orWhere('morada_entrega', 'like', "%{$q}%")
                            ->orWhere('fatura_morada', 'like', "%{$q}%");
                    }))
                )
                ->orderBy('corporate_id')
                ->get(),
            'corporates' => $corporatesDoDia,
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
                ->whereIn('corporate_id', $corporateIdsComEntrega)
                ->whereHas('corporate', fn ($query) => $query
                    ->where('ativo', true)
                    ->whereJsonContains('dias_entrega', $dia)
                )
                ->get()
                ->each(function (AtribuicaoEntrega $atribuicao) use ($data): void {
                    RegistoEntrega::firstOrCreate([
                        'corporate_id' => $atribuicao->corporate_id,
                        'user_id' => $atribuicao->user_id,
                        'data_entrega' => $data,
                    ]);
                });
        }

        $corporateIdsComEntrega ??= collect();

        $registos = RegistoEntrega::with(['corporate', 'user'])
            ->whereBetween('data_entrega', [$inicioPeriodo->toDateString(), $fimPeriodo->toDateString()])
            ->when($periodo === 'dia' && $dia !== null, fn ($query) => $query->whereIn('corporate_id', $corporateIdsComEntrega))
            ->when(in_array($status, ['pendente', 'entregue', 'falhou'], true), fn ($query) => $query->where('status', $status))
            ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
            ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                $query->where('corporates.empresa', 'like', "%{$q}%")
                    ->orWhere('corporates.sucursal', 'like', "%{$q}%")
                    ->orWhere('corporates.morada_entrega', 'like', "%{$q}%")
                    ->orWhere('corporates.fatura_morada', 'like', "%{$q}%");
            }))
            ->join('corporates', 'registo_entregas.corporate_id', '=', 'corporates.id')
            ->join('users', 'registo_entregas.user_id', '=', 'users.id')
            ->orderBy($sortColumn, $direction)
            ->orderBy('corporates.empresa')
            ->select('registo_entregas.*')
            ->get();

        $resumo = RegistoEntrega::whereBetween('data_entrega', [$inicioPeriodo->toDateString(), $fimPeriodo->toDateString()])
            ->when($periodo === 'dia' && $dia !== null, fn ($query) => $query->whereIn('corporate_id', $corporateIdsComEntrega))
            ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
            ->when(filled($q), fn ($query) => $query
                ->join('corporates', 'registo_entregas.corporate_id', '=', 'corporates.id')
                ->where(function ($query) use ($q): void {
                    $query->where('corporates.empresa', 'like', "%{$q}%")
                        ->orWhere('corporates.sucursal', 'like', "%{$q}%")
                        ->orWhere('corporates.morada_entrega', 'like', "%{$q}%")
                        ->orWhere('corporates.fatura_morada', 'like', "%{$q}%");
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

        $diaB2c = match ($dataSelecionada->dayOfWeek) {
            3 => 'quarta',
            6 => 'sabado',
            default => null,
        };

        $b2cOrders = WooOrder::query()
            ->where(function ($query): void {
                $query->whereIn('status', ['processing', 'on-hold', 'pending'])
                    ->orWhere('status', 'subscricao');
            })
            ->where(function ($query) use ($data): void {
                $query->whereNull('postponed_until')
                    ->orWhereDate('postponed_until', '<', $data);
            })
            ->when($diaB2c, fn ($query) => $query->where(function ($query) use ($diaB2c, $data): void {
                $query->whereJsonContains('delivery_dates', $data)
                    ->orWhereDate('scheduled_delivery_at', $data)
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
            }))
            ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                $query->where('billing_name', 'like', "%{$q}%")
                    ->orWhere('billing_phone', 'like', "%{$q}%")
                    ->orWhere('billing_email', 'like', "%{$q}%")
                    ->orWhere('woo_id', 'like', "%{$q}%");
            }))
            ->orderBy('billing_name')
            ->get();

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

        $frutas = ['banana', 'maca', 'pera', 'laranja', 'kiwi', 'uvas', 'fruta_epoca', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];
        $produtosKg = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];
        $totaisFrutas = collect($frutas)
            ->mapWithKeys(fn (string $fruta) => [
                $fruta => $corporates->sum(fn (Corporate $corporate) => (int) ($corporate->frutasParaDia($dia)[$fruta] ?? 0)),
            ])
            ->all();

        return view('entregas.preparacao', [
            'data' => $data,
            'dia' => $dia,
            'q' => $q,
            'dias' => array_values(self::DIAS),
            'corporates' => $corporates,
            'b2cOrders' => $b2cOrders,
            'preparacaoItems' => $preparacaoItems,
            'totalCaixas' => $corporates->sum('numero_caixas'),
            'totalPecas' => array_sum(collect($totaisFrutas)->except($produtosKg)->all()),
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

    public function storeAtribuicao(StoreAtribuicaoEntregaRequest $request): RedirectResponse
    {
        AtribuicaoEntrega::updateOrCreate(
            $request->safe()->only(['corporate_id', 'dia_semana']),
            ['user_id' => $request->validated('user_id')]
        );

        return back()->with('status', 'Atribuicao guardada.');
    }

    public function storeAtribuicoesBulk(BulkAtribuicaoEntregaRequest $request): RedirectResponse
    {
        $count = 0;

        foreach ($request->validated('corporate_ids') as $corporateId) {
            AtribuicaoEntrega::updateOrCreate(
                [
                    'corporate_id' => $corporateId,
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
        $atribuicao->update($request->safe()->only(['corporate_id', 'user_id', 'dia_semana']));

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
        $dia = self::DIAS[now()->dayOfWeek] ?? null;
        $data = now()->toDateString();
        $q = request('q', '');
        $status = request('status', '');

        $atribuicoes = AtribuicaoEntrega::with('corporate')
            ->where('user_id', auth()->id())
            ->when($dia, fn ($query) => $query->where('dia_semana', $dia))
            ->whereHas('corporate', fn ($query) => $query->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                $query->where('empresa', 'like', "%{$q}%")
                    ->orWhere('sucursal', 'like', "%{$q}%")
                    ->orWhere('morada_entrega', 'like', "%{$q}%")
                    ->orWhere('fatura_morada', 'like', "%{$q}%");
            })))
            ->get();
        $atribuicoes = $atribuicoes
            ->filter(fn (AtribuicaoEntrega $atribuicao) => $atribuicao->corporate->temEntregaNaData(now()))
            ->values();

        $registos = $atribuicoes->map(function (AtribuicaoEntrega $atribuicao) use ($data) {
            return RegistoEntrega::firstOrCreate([
                'corporate_id' => $atribuicao->corporate_id,
                'user_id' => $atribuicao->user_id,
                'data_entrega' => $data,
            ]);
        })->load('corporate')
            ->when(in_array($status, ['pendente', 'entregue', 'falhou'], true), fn ($collection) => $collection->where('status', $status)->values());

        return view('entregas.minhas', compact('registos', 'q', 'status'));
    }

    public function show(RegistoEntrega $registoEntrega): View
    {
        abort_unless(auth()->user()->isAdmin() || $registoEntrega->user_id === auth()->id(), 403);

        $registoEntrega->load(['corporate', 'user']);

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
}
