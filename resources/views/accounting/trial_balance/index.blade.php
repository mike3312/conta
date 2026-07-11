@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Balance de Comprobación</h1>
            <p class="text-muted mb-0">Saldos y movimientos de las cuentas de la empresa activa.</p>
        </div>

        @if($balanceStatus['is_balanced'])
            <span class="badge text-bg-success fs-6 px-3 py-2">
                <i class="bi bi-check-circle me-1"></i>Cuadrado
            </span>
        @else
            <span class="badge text-bg-danger fs-6 px-3 py-2">
                <i class="bi bi-exclamation-triangle me-1"></i>Descuadrado
            </span>
        @endif
    </div>

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>No se pudieron aplicar los filtros.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @unless($balanceStatus['is_balanced'])
        <div class="alert alert-warning">
            <strong>El balance presenta diferencias.</strong>
            Diferencia de movimientos: {{ $currency }} {{ number_format((float) $balanceStatus['movement_difference'], 2) }}.
            Diferencia de saldos finales: {{ $currency }} {{ number_format((float) $balanceStatus['final_difference'], 2) }}.
            No se realizaron correcciones automáticas.
        </div>
    @endunless

    @include('accounting.trial_balance._filters')

    @if($trialBalance->count())
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr class="text-center">
                                <th rowspan="2" class="align-middle">Código</th>
                                <th rowspan="2" class="align-middle">Cuenta</th>
                                <th rowspan="2" class="align-middle">Tipo</th>
                                <th rowspan="2" class="align-middle">Naturaleza</th>
                                <th colspan="2">Saldos anteriores</th>
                                <th colspan="2">Movimientos</th>
                                <th colspan="2">Saldos finales</th>
                            </tr>
                            <tr class="text-center">
                                <th>Deudor</th>
                                <th>Acreedor</th>
                                <th>Debe</th>
                                <th>Haber</th>
                                <th>Deudor</th>
                                <th>Acreedor</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($trialBalance as $row)
                                <tr>
                                    <td>{{ $row['account']->code }}</td>
                                    <td>
                                        {{ $row['account']->name }}
                                        @unless($row['is_normal_balance'])
                                            <span class="badge text-bg-warning ms-1">Saldo contrario</span>
                                        @endunless
                                    </td>
                                    <td>{{ $row['account']->account_type->label() }}</td>
                                    <td>{{ $row['account']->nature->label() }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) $row['previous_debit'], 2) }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) $row['previous_credit'], 2) }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) $row['range_debit'], 2) }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) $row['range_credit'], 2) }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) $row['final_debit'], 2) }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format((float) $row['final_credit'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-dark">
                            <tr>
                                <th colspan="4" class="text-end">Totales generales</th>
                                <th class="text-end">{{ $currency }} {{ number_format((float) $generalTotals->previous_debit, 2) }}</th>
                                <th class="text-end">{{ $currency }} {{ number_format((float) $generalTotals->previous_credit, 2) }}</th>
                                <th class="text-end">{{ $currency }} {{ number_format((float) $generalTotals->range_debit, 2) }}</th>
                                <th class="text-end">{{ $currency }} {{ number_format((float) $generalTotals->range_credit, 2) }}</th>
                                <th class="text-end">{{ $currency }} {{ number_format((float) $generalTotals->final_debit, 2) }}</th>
                                <th class="text-end">{{ $currency }} {{ number_format((float) $generalTotals->final_credit, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-4">
            {{ $trialBalance->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-clipboard-data fs-1 text-muted"></i>
                <p class="text-muted mt-3 mb-0">No existen cuentas con información para los filtros seleccionados.</p>
            </div>
        </div>
    @endif
</div>
@endsection
