<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCorporateHistoricoRequest;
use App\Http\Requests\StoreCorporateRequest;
use App\Http\Requests\UpdateCorporateRequest;
use App\Models\Corporate;
use App\Models\CorporateHistorico;
use App\Models\RegistoEntrega;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CorporateController extends Controller
{
    private const DIAS = ['Segunda', 'Terca', 'Quarta', 'Quinta', 'Sexta'];

    private const DIAS_SEMANA = [
        1 => 'Segunda',
        2 => 'Terca',
        3 => 'Quarta',
        4 => 'Quinta',
        5 => 'Sexta',
        6 => 'Sabado',
    ];

    private const FRUTAS = ['banana', 'maca', 'pera', 'laranja', 'kiwi', 'uvas', 'fruta_epoca', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

    private const PASTELARIA = ['pao', 'croissant', 'bolo'];

    private const PRODUTOS_KG = ['uvas', 'frutos_secos', 'mirtilos', 'framboesas', 'amoras', 'morangos'];

    public function index(Request $request): View
    {
        $q = $request->string('q')->toString();
        $dia = $request->string('dia')->toString();
        $ativo = $request->string('ativo')->toString();
        $periodicidade = $request->string('periodicidade_entrega')->toString();
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        $sortColumns = [
            'empresa' => 'empresa',
            'sucursal' => 'sucursal',
            'pecas' => 'peso_total',
            'caixas' => 'numero_caixas',
            'periodicidade' => 'periodicidade_entrega',
            'estado' => 'ativo',
        ];
        $sortColumn = $sortColumns[$sort] ?? 'empresa';

        return view('corporates.index', [
            'q' => $q,
            'dia' => $dia,
            'ativo' => $ativo,
            'periodicidade' => $periodicidade,
            'sort' => $sort ?: 'empresa',
            'direction' => $direction,
            'dias' => self::DIAS,
            'corporates' => Corporate::query()
                ->when(filled($q), fn ($query) => $query->where(function ($query) use ($q): void {
                    $query->where('empresa', 'like', "%{$q}%")
                        ->orWhere('sucursal', 'like', "%{$q}%")
                        ->orWhere('morada_entrega', 'like', "%{$q}%")
                        ->orWhere('fatura_morada', 'like', "%{$q}%")
                        ->orWhere('responsavel_nome', 'like', "%{$q}%")
                        ->orWhere('responsavel_telefone', 'like', "%{$q}%");
                }))
                ->when(in_array($dia, self::DIAS, true), fn ($query) => $query->whereJsonContains('dias_entrega', $dia))
                ->when(in_array($periodicidade, ['semanal', 'quinzenal'], true), fn ($query) => $query->where('periodicidade_entrega', $periodicidade))
                ->when($ativo === '1', fn ($query) => $query->where('ativo', true))
                ->when($ativo === '0', fn ($query) => $query->where('ativo', false))
                ->orderBy($sortColumn, $direction)
                ->orderBy('empresa')
                ->paginate(15)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('corporates.create', ['corporate' => new Corporate]);
    }

    public function export(): StreamedResponse
    {
        $corporates = Corporate::query()
            ->orderBy('empresa')
            ->orderBy('sucursal')
            ->get()
            ->map(fn (Corporate $corporate) => [
                'empresa' => $corporate->empresa,
                'sucursal' => $corporate->sucursal,
                'morada_entrega' => $corporate->morada_entrega,
                'dias_entrega' => $corporate->dias_entrega ?? [],
                'periodicidade_entrega' => $corporate->periodicidade_entrega ?? 'semanal',
                'quinzenal_referencia' => $corporate->quinzenal_referencia?->toDateString(),
                'horario_entrega' => $corporate->horario_entrega,
                'responsavel_nome' => $corporate->responsavel_nome,
                'responsavel_telefone' => $corporate->responsavel_telefone,
                'fatura_nome' => $corporate->fatura_nome,
                'fatura_nif' => $corporate->fatura_nif,
                'fatura_email' => $corporate->fatura_email,
                'fatura_morada' => $corporate->fatura_morada,
                'numero_caixas' => (int) $corporate->numero_caixas,
                'preco_venda_peca' => $corporate->preco_venda_peca !== null ? (float) $corporate->preco_venda_peca : null,
                'cabaz_tipo' => $corporate->cabaz_tipo,
                'cabaz_quantidade' => $corporate->cabaz_quantidade,
                'peso_total' => (float) $corporate->peso_total,
                'frutas' => $corporate->frutas ?? [],
                'frutas_por_dia' => $corporate->frutas_por_dia ?? [],
                'notas' => $corporate->notas,
                'ativo' => (bool) $corporate->ativo,
            ])
            ->values();

        $payload = json_encode([
            'exported_at' => now()->toIso8601String(),
            'count' => $corporates->count(),
            'corporates' => $corporates,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return response()->streamDownload(
            fn () => print($payload),
            'empresas-'.now()->format('Y-m-d-His').'.json',
            ['Content-Type' => 'application/json']
        );
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'ficheiro' => ['required', 'file', 'max:10240'],
        ]);

        try {
            $payload = json_decode((string) file_get_contents($request->file('ficheiro')->getRealPath()), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('JSON invalido: '.json_last_error_msg());
            }

            $rows = $payload['corporates'] ?? null;

            if (! is_array($rows)) {
                throw new RuntimeException('Formato invalido: falta a chave corporates.');
            }

            $created = 0;
            $updated = 0;

            DB::transaction(function () use ($rows, &$created, &$updated): void {
                foreach ($rows as $index => $row) {
                    if (! is_array($row)) {
                        throw new RuntimeException('Linha '.($index + 1).': esperado objeto/array de empresa.');
                    }

                    $data = $this->normalizeImportRow($row, $index + 1);
                    $keys = [
                        'empresa' => $data['empresa'],
                        'sucursal' => $data['sucursal'],
                    ];

                    Corporate::where($keys)->exists() ? $updated++ : $created++;
                    Corporate::updateOrCreate($keys, Arr::except($data, ['empresa', 'sucursal']));
                }
            });

            return redirect()->route('corporates.index')->with('status', "{$created} empresas criadas, {$updated} atualizadas.");
        } catch (RuntimeException $exception) {
            return back()->withErrors(['ficheiro' => $exception->getMessage()]);
        }
    }

    public function store(StoreCorporateRequest $request): RedirectResponse
    {
        Corporate::create($this->payload($request->validated()));

        return redirect()->route('corporates.index')->with('status', 'Empresa criada com sucesso.');
    }

    public function show(Corporate $corporate): View
    {
        $corporate->load([
            'atribuicoes.user',
            'registosEntrega.user',
            'historicos' => fn ($query) => $query->latest('data')->latest(),
            'historicos.user',
        ]);

        return view('corporates.show', compact('corporate'));
    }

    public function relatorioMensal(Request $request, Corporate $corporate): View
    {
        $validated = $request->validate([
            'mes' => ['nullable', 'date_format:Y-m'],
        ]);
        $mes = $validated['mes'] ?? now()->format('Y-m');
        $inicio = Carbon::createFromFormat('Y-m-d', "{$mes}-01")->startOfDay();
        $fim = $inicio->copy()->endOfMonth();
        $diasEntrega = $corporate->dias_entrega ?? [];

        $registos = RegistoEntrega::query()
            ->where('corporate_id', $corporate->id)
            ->whereBetween('data_entrega', [$inicio->toDateString(), $fim->toDateString()])
            ->get()
            ->keyBy(fn (RegistoEntrega $registo): string => $registo->data_entrega->toDateString());

        $historicosNaoEntregamos = $corporate->historicos()
            ->where('tipo', 'nao_entregamos')
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->get()
            ->keyBy(fn (CorporateHistorico $historico): string => $historico->data->toDateString());

        $linhas = collect();
        $data = $inicio->copy();

        while ($data->lessThanOrEqualTo($fim)) {
            $diaSemana = self::DIAS_SEMANA[$data->dayOfWeek] ?? null;

            if ($diaSemana !== null && in_array($diaSemana, $diasEntrega, true) && $corporate->temEntregaNaData($data)) {
                $dataKey = $data->toDateString();
                $registo = $registos->get($dataKey);
                $historicoNaoEntregamos = $historicosNaoEntregamos->get($dataKey);
                $estado = $historicoNaoEntregamos !== null
                    ? 'nao_entregamos'
                    : ($registo?->status ?? 'sem_registo');

                $linhas->push([
                    'data' => $data->copy(),
                    'dia' => $diaSemana,
                    'estado' => $estado,
                    'hora_entrega' => $registo?->hora_entrega,
                    'nota' => collect([$registo?->nota, $historicoNaoEntregamos?->texto])->filter()->implode("\n"),
                ]);
            }

            $data->addDay();
        }

        $totais = [
            'entregue' => $linhas->where('estado', 'entregue')->count(),
            'falhou' => $linhas->where('estado', 'falhou')->count(),
            'nao_entregamos' => $linhas->where('estado', 'nao_entregamos')->count(),
            'sem_registo' => $linhas->where('estado', 'sem_registo')->count(),
        ];

        return view('corporates.relatorio-mensal', [
            'corporate' => $corporate,
            'mes' => $mes,
            'inicio' => $inicio,
            'linhas' => $linhas,
            'totais' => $totais,
        ]);
    }

    public function edit(Corporate $corporate): View
    {
        return view('corporates.edit', compact('corporate'));
    }

    public function update(UpdateCorporateRequest $request, Corporate $corporate): RedirectResponse
    {
        $corporate->update($this->payload($request->validated()));

        return redirect()->route('corporates.index')->with('status', 'Empresa atualizada com sucesso.');
    }

    public function destroy(Corporate $corporate): RedirectResponse
    {
        $corporate->delete();

        return redirect()->route('corporates.index')->with('status', 'Empresa removida.');
    }

    public function storeHistorico(StoreCorporateHistoricoRequest $request, Corporate $corporate): RedirectResponse
    {
        $corporate->historicos()->create([
            ...$request->validated(),
            'tipo' => $request->validated('tipo') ?: 'nota',
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'Historico adicionado.');
    }

    public function destroyHistorico(Corporate $corporate, CorporateHistorico $historico): RedirectResponse
    {
        abort_unless($historico->corporate_id === $corporate->id, 404);

        $historico->delete();

        return back()->with('status', 'Historico removido.');
    }

    private function payload(array $data): array
    {
        $frutas = collect(self::FRUTAS)
            ->mapWithKeys(fn (string $fruta) => [$fruta => $this->quantidadeFruta($data['frutas'][$fruta] ?? 0, $fruta)])
            ->all();

        $frutasPorDia = collect(self::DIAS)
            ->mapWithKeys(function (string $dia) use ($data, $frutas): array {
                $valoresDia = collect(self::FRUTAS)
                    ->mapWithKeys(fn (string $fruta) => [$fruta => $this->quantidadeFruta($data['frutas_por_dia'][$dia][$fruta] ?? 0, $fruta)])
                    ->all();

                if (in_array($dia, $data['dias_entrega'] ?? [], true) && array_sum($valoresDia) <= 0) {
                    $valoresDia = $frutas;
                }

                return [$dia => $valoresDia];
            })
            ->filter(fn (array $frutas) => array_sum($frutas) > 0)
            ->all();
        $pastelaria = $this->quantidadesPastelaria($data['pastelaria'] ?? []);
        $pastelariaPorDia = collect(self::DIAS)
            ->mapWithKeys(function (string $dia) use ($data, $pastelaria): array {
                $valoresDia = $this->quantidadesPastelaria($data['pastelaria_por_dia'][$dia] ?? []);

                if (in_array($dia, $data['dias_entrega'] ?? [], true) && array_sum($valoresDia) <= 0) {
                    $valoresDia = $pastelaria;
                }

                return [$dia => $valoresDia];
            })
            ->filter(fn (array $pastelaria) => array_sum($pastelaria) > 0)
            ->all();
        $pesoTotal = collect($data['dias_entrega'] ?? [])
            ->sum(fn (string $dia) => (int) array_sum(collect($frutasPorDia[$dia] ?? [])->except(self::PRODUTOS_KG)->all()));

        return [
            ...$data,
            'cabaz_tipo' => filled($data['cabaz_tipo'] ?? null) ? $data['cabaz_tipo'] : null,
            'cabaz_quantidade' => filled($data['cabaz_tipo'] ?? null) ? (int) ($data['cabaz_quantidade'] ?? 1) : null,
            'frutas' => $frutas,
            'frutas_por_dia' => $frutasPorDia,
            'pastelaria' => $pastelaria,
            'pastelaria_por_dia' => $pastelariaPorDia,
            'peso_total' => $pesoTotal,
            'ativo' => (bool) ($data['ativo'] ?? false),
            'preco_venda_peca' => filled($data['preco_venda_peca'] ?? null) ? (float) $data['preco_venda_peca'] : null,
            'quinzenal_referencia' => $data['periodicidade_entrega'] === 'quinzenal' ? ($data['quinzenal_referencia'] ?? null) : null,
        ];
    }

    private function quantidadeFruta(mixed $value, string $fruta): int|float
    {
        if (in_array($fruta, self::PRODUTOS_KG, true)) {
            return round(max(0, (float) $value), 2);
        }

        return (int) $value;
    }

    private function quantidadesPastelaria(mixed $values): array
    {
        $values = is_array($values) ? $values : [];

        return collect(self::PASTELARIA)
            ->mapWithKeys(fn (string $produto): array => [$produto => max(0, (int) ($values[$produto] ?? 0))])
            ->all();
    }

    private function normalizeImportRow(array $row, int $line): array
    {
        $diasEntrega = is_array($row['dias_entrega'] ?? null) ? $row['dias_entrega'] : [];
        $row = [
            ...$row,
            'empresa' => trim((string) ($row['empresa'] ?? '')),
            'sucursal' => $this->nullableString($row['sucursal'] ?? null),
            'dias_entrega' => array_values(array_filter($diasEntrega, fn (mixed $dia): bool => filled($dia))),
        ];

        $validator = Validator::make($row, [
            'empresa' => ['required', 'string', 'max:255'],
            'sucursal' => ['nullable', 'string', 'max:255'],
            'morada_entrega' => ['nullable', 'string', 'max:500'],
            'dias_entrega' => ['array'],
            'dias_entrega.*' => ['in:Segunda,Terca,Quarta,Quinta,Sexta'],
            'periodicidade_entrega' => ['nullable', 'in:semanal,quinzenal'],
            'quinzenal_referencia' => ['nullable', 'date'],
            'cabaz_tipo' => ['nullable', 'in:pequeno,medio,grande'],
            'cabaz_quantidade' => ['nullable', 'integer', 'min:1'],
            'preco_venda_peca' => ['nullable', 'numeric', 'min:0'],
            'fatura_email' => ['nullable', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new RuntimeException('Linha '.$line.': '.$validator->errors()->first());
        }

        $periodicidade = in_array($row['periodicidade_entrega'] ?? null, ['semanal', 'quinzenal'], true)
            ? $row['periodicidade_entrega']
            : 'semanal';
        $frutas = $this->normalizeImportFruits($row['frutas'] ?? []);
        $frutasPorDia = collect($row['frutas_por_dia'] ?? [])
            ->filter(fn (mixed $values) => is_array($values))
            ->map(fn (array $values) => $this->normalizeImportFruits($values))
            ->filter(fn (array $values) => array_sum($values) > 0)
            ->all();

        return [
            'empresa' => $row['empresa'],
            'sucursal' => $row['sucursal'],
            'morada_entrega' => $this->nullableString($row['morada_entrega'] ?? null),
            'dias_entrega' => $row['dias_entrega'],
            'periodicidade_entrega' => $periodicidade,
            'quinzenal_referencia' => $periodicidade === 'quinzenal' ? ($row['quinzenal_referencia'] ?? null) : null,
            'horario_entrega' => $this->nullableString($row['horario_entrega'] ?? null),
            'responsavel_nome' => $this->nullableString($row['responsavel_nome'] ?? null),
            'responsavel_telefone' => $this->nullableString($row['responsavel_telefone'] ?? null),
            'fatura_nome' => $this->nullableString($row['fatura_nome'] ?? null),
            'fatura_nif' => $this->nullableString($row['fatura_nif'] ?? null),
            'fatura_email' => $this->nullableString($row['fatura_email'] ?? null),
            'fatura_morada' => $this->nullableString($row['fatura_morada'] ?? null),
            'numero_caixas' => max(0, (int) ($row['numero_caixas'] ?? 0)),
            'preco_venda_peca' => $this->nullableDecimal($row['preco_venda_peca'] ?? null),
            'cabaz_tipo' => in_array($row['cabaz_tipo'] ?? null, ['pequeno', 'medio', 'grande'], true) ? $row['cabaz_tipo'] : null,
            'cabaz_quantidade' => filled($row['cabaz_tipo'] ?? null) ? max(1, (int) ($row['cabaz_quantidade'] ?? 1)) : null,
            'peso_total' => max(0, (float) ($row['peso_total'] ?? 0)),
            'frutas' => $frutas,
            'frutas_por_dia' => $frutasPorDia,
            'notas' => $this->nullableString($row['notas'] ?? null),
            'ativo' => $this->boolValue($row['ativo'] ?? true),
        ];
    }

    private function normalizeImportFruits(array $values): array
    {
        return collect(self::FRUTAS)
            ->mapWithKeys(fn (string $fruta) => [$fruta => in_array($fruta, self::PRODUTOS_KG, true)
                ? round(max(0, (float) ($values[$fruta] ?? 0)), 2)
                : max(0, (int) ($values[$fruta] ?? 0))])
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return max(0, (float) $value);
    }

    private function boolValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
}
