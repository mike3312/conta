<!doctype html><html><head>@include('accounting.exports._pdf_head')</head><body>
@php($reportTitle = 'Balance General')
@php($periodText = 'Al '.$filters['cutoff_date'])
@include('accounting.exports._pdf_header')
@if($hasInformation)
@foreach([['Activos',$assetSection,$totalAssets],['Pasivos',$liabilitySection,$totalLiabilities],['Patrimonio',$equitySection,$baseEquity]] as [$title,$section,$total])
<h2>{{ $title }}</h2><table><thead><tr><th>Código</th><th>Cuenta</th><th>Saldo</th></tr></thead><tbody>@include('accounting.exports._hierarchy_rows',['nodes'=>$section,'level'=>0])<tr class="total"><td colspan="2">Total {{ strtolower($title) }}</td><td class="num">Q {{ number_format((float)$total,2) }}</td></tr></tbody></table>
@endforeach
<table><tbody><tr class="total"><td>Resultado pendiente</td><td class="num">Q {{ number_format((float)$pendingResult,2) }}</td></tr><tr class="total"><td>Total patrimonio</td><td class="num">Q {{ number_format((float)$totalEquity,2) }}</td></tr><tr class="result"><td>Total pasivos + patrimonio</td><td class="num">Q {{ number_format((float)$liabilitiesAndEquity,2) }}</td></tr><tr class="{{ $isBalanced ? 'total' : 'warning' }}"><td>Diferencia {{ $isBalanced ? '(cuadrado)' : '(advertencia: no cuadra)' }}</td><td class="num">Q {{ number_format((float)$difference,2) }}</td></tr></tbody></table>
@else<div class="empty">No existen datos para los filtros seleccionados.</div>@endif
</body></html>
