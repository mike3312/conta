<!doctype html><html><head>@include('accounting.exports._pdf_head')</head><body>
@php($reportTitle = 'Libro Mayor')
@php($periodText = !empty($filters['date_from']) || !empty($filters['date_to']) ? 'Rango: '.($filters['date_from'] ?? 'inicio').' al '.($filters['date_to'] ?? 'actualidad') : 'Todos los períodos')
@include('accounting.exports._pdf_header')
@forelse($ledgerAccounts as $ledger)
    <h2>{{ $ledger['account']->code }} - {{ $ledger['account']->name }} · Naturaleza {{ $ledger['account']->nature->label() }}</h2>
    <table><thead><tr><th>Fecha</th><th>Póliza</th><th>Concepto</th><th>Debe</th><th>Haber</th><th>Saldo acumulado</th></tr></thead><tbody>
    <tr class="section"><td colspan="5">Saldo inicial ({{ $ledger['previous_balance_type'] }})</td><td class="num">Q {{ number_format((float)$ledger['previous_balance'], 2) }}</td></tr>
    @forelse($ledger['movements'] as $movement)
        <tr><td>{{ \Carbon\Carbon::parse($movement['entry_date'])->format('d/m/Y') }}</td><td>{{ $movement['number'] }}</td><td>{{ $movement['entry_description'] }}@if($movement['line_description']) · {{ $movement['line_description'] }}@endif</td><td class="num">Q {{ number_format((float)$movement['debit'],2) }}</td><td class="num">Q {{ number_format((float)$movement['credit'],2) }}</td><td class="num">Q {{ number_format((float)$movement['balance'],2) }} {{ $movement['balance_type'] }}</td></tr>
    @empty<tr><td colspan="6" class="empty">Sin movimientos dentro del rango.</td></tr>@endforelse
    <tr class="total"><td colspan="3">Totales / saldo final</td><td class="num">Q {{ number_format((float)$ledger['total_debit'],2) }}</td><td class="num">Q {{ number_format((float)$ledger['total_credit'],2) }}</td><td class="num">Q {{ number_format((float)$ledger['final_balance'],2) }} {{ $ledger['final_balance_type'] }}</td></tr>
    </tbody></table>
@empty<div class="empty">No existen datos para los filtros seleccionados.</div>@endforelse
</body></html>
