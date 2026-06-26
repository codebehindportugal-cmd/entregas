<!DOCTYPE html>
<html lang="pt">
<head>
<meta charset="UTF-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 20px; }
h1 { font-size: 16px; margin: 0 0 2px; }
.subtitle { color: #444; font-size: 11px; margin: 0 0 2px; }
.meta { color: #888; font-size: 9px; margin: 0 0 14px; }
.section-title { font-size: 9px; text-transform: uppercase; letter-spacing: .5px; color: #666; font-weight: bold; margin: 16px 0 6px; border-bottom: 1px solid #ddd; padding-bottom: 3px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
th { background: #f0f0f0; padding: 4px 6px; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: .4px; border-bottom: 2px solid #ccc; }
td { padding: 4px 6px; border-bottom: 1px solid #eee; vertical-align: top; font-size: 9.5px; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.bold { font-weight: bold; }
.total-bar { display: flex; gap: 20px; background: #f7f7f7; padding: 8px 10px; border-radius: 4px; margin-bottom: 14px; border: 1px solid #ddd; }
.total-label { font-size: 8px; color: #666; text-transform: uppercase; font-weight: bold; }
.total-valor { font-size: 13px; font-weight: bold; color: #111; }
.total-bar { background: #1a3a1a; color: white; padding: 6px 10px; margin-left: auto; text-align: right; border-radius: 3px; display: inline-block; font-size: 13px; font-weight: bold; }
.despesa-header { background: #f5f5f5; font-weight: bold; font-size: 9.5px; }
.sub-item td { background: #fafafa; font-size: 9px; color: #444; padding: 3px 6px 3px 18px; }
tfoot td { font-weight: bold; border-top: 2px solid #ccc; border-bottom: none; font-size: 10px; padding-top: 6px; }
.badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7.5px; font-weight: bold; border: 1px solid #ccc; }
</style>
</head>
<body>
<h1>Despesas e Faturas &mdash; Horta da Maria</h1>
<p class="meta">{{ $inicio->translatedFormat('F Y') }} &nbsp;&middot;&nbsp; Gerado em {{ now()->format('d/m/Y H:i') }}</p>

<div class="total-bar">
    <div>
        <div class="total-label">Total</div>
        <div class="total-valor" style="color:#166534;">{{ number_format($total, 2, ',', ' ') }} EUR</div>
    </div>
</div>

{{-- Detalhe por despesa --}}
<p class="section-title">Detalhe de faturas</p>
<table>
<thead>
  <tr>
    <th>Data</th>
    <th>Titulo / Fornecedor</th>
    <th>Linhas</th>
    <th class="text-right">Valor</th>
  </tr>
</thead>
<tbody>
@forelse($despesas as $despesa)
  <tr class="despesa-header">
    <td>{{ $despesa->data->format('d/m/Y') }}</td>
    <td>
      {{ $despesa->titulo }}
      @if($despesa->numero_fatura) <span style="color:#666;font-size:8px;"> N.{{ $despesa->numero_fatura }}</span>@endif
      @if($despesa->fornecedor) <br><span style="color:#666;font-size:8.5px;">{{ $despesa->fornecedor }}</span>@endif
    </td>
    <td></td>
    <td class="text-right bold">{{ number_format($despesa->total_fatura, 2, ',', ' ') }} EUR</td>
  </tr>
  @if($despesa->items->isNotEmpty())
    @foreach($despesa->items as $item)
    <tr class="sub-item">
      <td></td>
      <td>{{ $item->descricao }}</td>
      <td style="color:#888;font-size:8px;">
        {{ number_format((float)$item->quantidade, 3, ',', '') }} {{ $item->unidade_compra ?? 'un' }} x {{ number_format((float)$item->preco_unitario, 4, ',', '') }} EUR
        @if((float) $item->quantidade_unidades > 0)
          &nbsp; | &nbsp; {{ number_format((float)$item->quantidade_unidades, 3, ',', '') }} un
          @if($item->custo_unitario !== null)
            ({{ number_format($item->custo_unitario, 4, ',', '') }} EUR/un s/ IVA)
          @endif
        @endif
        + IVA {{ number_format((float)$item->iva_percentagem, 0) }}%
      </td>
      <td class="text-right">{{ number_format($item->total_com_iva, 2, ',', '') }} EUR</td>
    </tr>
    @endforeach
    <tr class="sub-item">
      <td colspan="3" class="text-right" style="color:#555;">
        Subtotal s/ IVA: {{ number_format($despesa->subtotal_calculado, 2, ',', '') }} EUR
        &nbsp; IVA: {{ number_format($despesa->iva_calculado, 2, ',', '') }} EUR
      </td>
      <td></td>
    </tr>
  @endif
@empty
  <tr><td colspan="4" style="text-align:center;color:#888;padding:14px;">Sem despesas neste periodo.</td></tr>
@endforelse
</tbody>
<tfoot>
  <tr>
    <td colspan="2">
      Subtotal s/ IVA: {{ number_format($subtotal, 2, ',', ' ') }} EUR
      &nbsp;&middot;&nbsp; IVA total: {{ number_format($ivaTotal, 2, ',', ' ') }} EUR
    </td>
    <td class="text-right">Total c/ IVA:</td>
    <td class="text-right">{{ number_format($total, 2, ',', ' ') }} EUR</td>
  </tr>
</tfoot>
</table>
</body>
</html>
