<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 24px; }
h1 { font-size: 15px; margin: 0 0 2px; }
.subtitle { color: #555; font-size: 11px; margin: 0 0 4px; }
.meta { color: #888; font-size: 9px; margin: 0 0 18px; }
table { width: 100%; border-collapse: collapse; }
th { background: #f0f0f0; padding: 5px 8px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; border-bottom: 2px solid #ccc; }
td { padding: 5px 8px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
.badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 9px; font-weight: bold; }
.entregue { background: #dcfce7; color: #166534; }
.falhou { background: #fee2e2; color: #991b1b; }
.parcial { background: #fef9c3; color: #854d0e; }
.nao_entregamos { background: #f1f5f9; color: #475569; }
.sem_registo { background: #fef9c3; color: #854d0e; }
tfoot td { font-weight: bold; padding-top: 10px; border-top: 2px solid #ccc; border-bottom: none; font-size: 10px; }
</style>
</head>
<body>
<h1>Relatorio Mensal &mdash; {{ $corporate->empresa }}</h1>
@if($corporate->sucursal)<p class="subtitle">{{ $corporate->sucursal }}</p>@endif
<p class="meta">{{ $inicio->format('F Y') }} &nbsp;&middot;&nbsp; Gerado em {{ now()->format('d/m/Y H:i') }}</p>

<table>
<thead>
  <tr>
    <th style="width:85px">Data</th>
    <th style="width:65px">Dia</th>
    <th style="width:115px">Estado</th>
    <th style="width:50px">Hora</th>
    <th>Nota</th>
  </tr>
</thead>
<tbody>
@forelse($linhas as $linha)
  <tr>
    <td>{{ $linha['data']->format('d/m/Y') }}</td>
    <td>{{ $linha['dia'] }}</td>
    <td>
      @if($linha['estado'] === 'entregue')<span class="badge entregue">Entregue</span>
      @elseif($linha['estado'] === 'falhou')<span class="badge falhou">Falhou</span>
      @elseif($linha['estado'] === 'nao_entregamos')<span class="badge nao_entregamos">! Nao entregamos</span>
      @elseif($linha['estado'] === 'entrega_parcial')<span class="badge parcial">Entrega parcial</span>
      @else<span class="badge sem_registo">Sem registo</span>@endif
    </td>
    <td>{{ $linha['hora_entrega']?->format('H:i') ?: '-' }}</td>
    <td>{{ $linha['pecas_entregues'] !== null ? $linha['pecas_entregues'].' pecas'.($linha['nota'] ? ' · '.$linha['nota'] : '') : ($linha['nota'] ?: '-') }}</td>
  </tr>
@empty
  <tr><td colspan="5" style="text-align:center;color:#888;padding:16px">Nao existem dias de entrega previstos neste mes.</td></tr>
@endforelse
</tbody>
<tfoot>
  <tr>
    <td colspan="5">
      {{ $totais['entregue'] }} entregas feitas &nbsp;&middot;&nbsp;
      {{ $totais['falhou'] }} falhas &nbsp;&middot;&nbsp;
      {{ $totais['entrega_parcial'] }} parciais &nbsp;&middot;&nbsp;
      {{ $totais['nao_entregamos'] }} nao entregamos &nbsp;&middot;&nbsp;
      {{ $totais['sem_registo'] }} sem registo
    </td>
  </tr>
</tfoot>
</table>
</body>
</html>
