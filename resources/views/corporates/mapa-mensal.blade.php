<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mapa mensal - {{ $corporate->empresa }} - {{ $inicio->format('m/Y') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            body {
                background: #fff !important;
                color: #111 !important;
            }

            .no-print {
                display: none !important;
            }

            .sheet {
                margin: 0 !important;
                max-width: none !important;
                padding: 0 !important;
                box-shadow: none !important;
            }

            table {
                border-collapse: collapse !important;
            }

            th,
            td {
                border: 1px solid #222 !important;
                color: #111 !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-950 antialiased">
    <div class="sheet mx-auto min-h-screen max-w-5xl bg-white px-6 py-6 shadow-sm sm:px-10 sm:py-8">
        <div class="no-print mb-6 flex flex-wrap items-end justify-between gap-3 rounded border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('corporates.show', $corporate) }}" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Voltar</a>
                <a href="{{ route('corporates.mapa-mensal', [$corporate, 'mes' => $mesAnterior]) }}" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Mes anterior</a>
                <a href="{{ route('corporates.mapa-mensal', [$corporate, 'mes' => $mesSeguinte]) }}" class="rounded border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Mes seguinte</a>
                <button type="button" onclick="window.print()" class="rounded bg-[#3B82F6] px-4 py-2 text-sm font-semibold text-white">Imprimir</button>
            </div>
            <form method="get" action="{{ route('corporates.mapa-mensal', $corporate) }}" class="flex items-end gap-2">
                <label class="text-sm font-medium text-slate-700">Mes
                    <input name="mes" type="month" value="{{ $mes }}" class="mt-1 rounded border border-slate-200 bg-white px-3 py-2 text-slate-950">
                </label>
                <button class="rounded bg-[#22C55E] px-4 py-2 text-sm font-semibold text-[#0A0F1A]">Ver</button>
            </form>
        </div>

        <header class="flex flex-wrap items-start justify-between gap-6 border-b border-slate-200 pb-6">
            <div class="flex items-center gap-4">
                <span class="flex h-16 w-16 items-center justify-center rounded border border-slate-200 bg-white p-2">
                    <img src="{{ asset('images/horta-da-maria-logo.png') }}" alt="Horta da Maria" class="h-full w-full object-contain">
                </span>
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700">Horta da Maria</p>
                    <h1 class="mt-1 text-2xl font-semibold text-slate-950">Mapa mensal de entregas</h1>
                </div>
            </div>
            <div class="text-right text-sm text-slate-600">
                <p class="text-lg font-semibold text-slate-950">{{ $corporate->empresa }}</p>
                @if($corporate->sucursal)
                    <p>{{ $corporate->sucursal }}</p>
                @endif
                <p>{{ $inicio->translatedFormat('F Y') }}</p>
                <p>{{ $corporate->moradaParaEntrega() ?: 'Morada nao definida' }}</p>
            </div>
        </header>

        <main class="mt-8">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-slate-100">
                        <th class="border border-slate-300 px-3 py-2">Data</th>
                        <th class="border border-slate-300 px-3 py-2">Dia</th>
                        <th class="border border-slate-300 px-3 py-2 text-right">No de Pecas</th>
                        <th class="border border-slate-300 px-3 py-2">Observacao</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($linhas as $linha)
                        <tr>
                            <td class="border border-slate-300 px-3 py-2">{{ $linha['data']->format('d/m/Y') }}</td>
                            <td class="border border-slate-300 px-3 py-2">{{ $linha['dia_semana'] }}</td>
                            <td class="border border-slate-300 px-3 py-2 text-right font-semibold">{{ $linha['pecas'] }}</td>
                            <td class="whitespace-pre-line border border-slate-300 px-3 py-2">
                                @if($linha['status'] === 'nao_entregamos')
                                    Nao entregue{{ $linha['nota'] ? "\n".$linha['nota'] : '' }}
                                @elseif($linha['status'] === 'entrega_parcial')
                                    Entrega parcial{{ $linha['nota'] ? "\n".$linha['nota'] : '' }}
                                @elseif($linha['status'] === 'falhou')
                                    {{ $linha['nota'] ?: 'Nao entregue' }}
                                @else
                                    {{ $linha['nota'] }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="border border-slate-300 px-3 py-6 text-center text-slate-500">Nao existem dias de entrega previstos neste mes.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="bg-slate-100 font-semibold">
                        <td colspan="2" class="border border-slate-300 px-3 py-2">Total de dias entregues</td>
                        <td colspan="2" class="border border-slate-300 px-3 py-2">{{ $totalDiasEntregues }}</td>
                    </tr>
                    <tr class="bg-slate-100 font-semibold">
                        <td colspan="2" class="border border-slate-300 px-3 py-2">Total de pecas entregues no mes</td>
                        <td colspan="2" class="border border-slate-300 px-3 py-2">{{ $totalPecasEntregues }}</td>
                    </tr>
                </tfoot>
            </table>
        </main>
    </div>
</body>
</html>
