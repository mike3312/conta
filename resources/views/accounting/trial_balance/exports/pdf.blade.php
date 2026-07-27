<!doctype html><html><head>@include('accounting.exports._pdf_head')</head><body>
@php($reportTitle = 'Balance de Comprobación')
@php($periodText = 'Rango: '.($filters['date_from'] ?? 'inicio').' al '.($filters['date_to'] ?? 'actualidad'))
@include('accounting.exports._pdf_header')
<table><thead><tr><th>Código</th><th>Cuenta</th><th>Inicial deudor</th><th>Inicial acreedor</th><th>Debe</th><th>Haber</th><th>Final deudor</th><th>Final acreedor</th></tr></thead><tbody>
@forelse($trialBalance as $row)<tr><td>{{ $row['account']->code }}</td><td style="padding-left:{{ max(0,(int)$row['account']->level-1)*8+4 }}px">{{ $row['account']->name }}</td><td class="num">{{ number_format((float)$row['previous_debit'],2) }}</td><td class="num">{{ number_format((float)$row['previous_credit'],2) }}</td><td class="num">{{ number_format((float)$row['range_debit'],2) }}</td><td class="num">{{ number_format((float)$row['range_credit'],2) }}</td><td class="num">{{ number_format((float)$row['final_debit'],2) }}</td><td class="num">{{ number_format((float)$row['final_credit'],2) }}</td></tr>
@empty<tr><td colspan="8" class="empty">No existen datos para los filtros seleccionados.</td></tr>@endforelse
<tr class="total"><td colspan="2">Totales generales</td><td class="num">{{ number_format((float)$generalTotals->previous_debit,2) }}</td><td class="num">{{ number_format((float)$generalTotals->previous_credit,2) }}</td><td class="num">{{ number_format((float)$generalTotals->range_debit,2) }}</td><td class="num">{{ number_format((float)$generalTotals->range_credit,2) }}</td><td class="num">{{ number_format((float)$generalTotals->final_debit,2) }}</td><td class="num">{{ number_format((float)$generalTotals->final_credit,2) }}</td></tr>
</tbody></table>
@unless($balanceStatus['is_balanced'])<p class="warning">Advertencia: el balance presenta diferencias. No se realizaron correcciones automáticas.</p>@endunless
</body></html>
