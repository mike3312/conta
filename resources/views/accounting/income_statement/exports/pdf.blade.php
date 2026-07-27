<!doctype html><html><head>@include('accounting.exports._pdf_head')</head><body>
@php($reportTitle = 'Estado de Resultados')
@php($periodText = 'Rango: '.($filters['date_from'] ?? 'inicio').' al '.($filters['date_to'] ?? 'actualidad'))
@include('accounting.exports._pdf_header')
@if($hasInformation)
<h2>Ingresos</h2><table><thead><tr><th>Código</th><th>Cuenta</th><th>Importe</th></tr></thead><tbody>@include('accounting.exports._hierarchy_rows',['nodes'=>$incomeSection,'level'=>0])<tr class="total"><td colspan="2">Ingresos totales</td><td class="num">Q {{ number_format((float)$totalIncome,2) }}</td></tr></tbody></table>
<h2>Gastos</h2><table><thead><tr><th>Código</th><th>Cuenta</th><th>Importe</th></tr></thead><tbody>@include('accounting.exports._hierarchy_rows',['nodes'=>$expenseSection,'level'=>0])<tr class="total"><td colspan="2">Gastos totales</td><td class="num">Q {{ number_format((float)$totalExpense,2) }}</td></tr><tr class="result"><td colspan="2">{{ $resultType === 'loss' ? 'Pérdida neta' : 'Utilidad neta' }}</td><td class="num">Q {{ number_format((float)$result,2) }}</td></tr></tbody></table>
@else<div class="empty">No existen datos para los filtros seleccionados.</div>@endif
</body></html>
