@extends('layouts.app')

@section('content')
<style>
@media print {
    body > .d-flex > .bg-dark,
    nav.navbar,
    footer,
    .income-statement-no-print {
        display: none !important;
    }

    body,
    main.container-fluid,
    .income-statement-report {
        background: #fff !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .income-statement-report .card {
        border: 0 !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
}
</style>

<div class="container py-4 income-statement-report">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Estado de Resultados</h1>
            <h2 class="h5 mb-1">{{ $company->name }}</h2>
            <p class="text-muted mb-0">
                Del {{ isset($filters['date_from']) ? \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') : 'inicio' }}
                al {{ isset($filters['date_to']) ? \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') : 'final' }}
                · Moneda: {{ $company->currency }}
            </p>
        </div>

        <button type="button" class="btn btn-outline-secondary income-statement-no-print" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Imprimir
        </button>
    </div>

    @if($errors->any())
        <div class="alert alert-danger income-statement-no-print">
            <strong>No se pudieron aplicar los filtros.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('accounting.income_statement._filters')

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-success">
                <div class="card-body">
                    <span class="text-muted d-block">Total de ingresos</span>
                    <strong class="fs-4 text-success">{{ $company->currency }} {{ number_format((float) $totalIncome, 2) }}</strong>
                    @if($incomeIsContrary)<span class="badge text-bg-warning d-block mt-2">Saldo contrario</span>@endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-danger">
                <div class="card-body">
                    <span class="text-muted d-block">Total de gastos</span>
                    <strong class="fs-4 text-danger">{{ $company->currency }} {{ number_format((float) $totalExpense, 2) }}</strong>
                    @if($expenseIsContrary)<span class="badge text-bg-warning d-block mt-2">Saldo contrario</span>@endif
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card shadow-sm h-100 {{ $resultType === 'profit' ? 'border-success' : ($resultType === 'loss' ? 'border-danger' : 'border-secondary') }}">
                <div class="card-body">
                    <span class="text-muted d-block">
                        {{ $resultType === 'profit' ? 'Utilidad del período' : ($resultType === 'loss' ? 'Pérdida del período' : 'Resultado del período') }}
                    </span>
                    <strong class="fs-4 {{ $resultType === 'profit' ? 'text-success' : ($resultType === 'loss' ? 'text-danger' : '') }}">
                        {{ $company->currency }} {{ number_format((float) $result, 2) }}
                    </strong>
                </div>
            </div>
        </div>
    </div>

    @if($hasInformation)
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white"><strong>Ingresos</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Cuenta</th>
                                @if($filters['show_details'])
                                    <th class="text-end">Debe</th>
                                    <th class="text-end">Haber</th>
                                @endif
                                <th class="text-end">Ingreso neto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @include('accounting.income_statement._account_rows', [
                                'nodes' => $incomeSection,
                                'level' => 0,
                                'type' => 'income',
                                'currency' => $company->currency,
                                'showDetails' => $filters['show_details'],
                            ])
                        </tbody>
                        <tfoot class="table-success">
                            <tr>
                                <th colspan="{{ $filters['show_details'] ? 4 : 2 }}" class="text-end">Total de ingresos</th>
                                <th class="text-end">{{ $company->currency }} {{ number_format((float) $totalIncome, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-danger text-white"><strong>Gastos</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Cuenta</th>
                                @if($filters['show_details'])
                                    <th class="text-end">Debe</th>
                                    <th class="text-end">Haber</th>
                                @endif
                                <th class="text-end">Gasto neto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @include('accounting.income_statement._account_rows', [
                                'nodes' => $expenseSection,
                                'level' => 0,
                                'type' => 'expense',
                                'currency' => $company->currency,
                                'showDetails' => $filters['show_details'],
                            ])
                        </tbody>
                        <tfoot class="table-danger">
                            <tr>
                                <th colspan="{{ $filters['show_details'] ? 4 : 2 }}" class="text-end">Total de gastos</th>
                                <th class="text-end">{{ $company->currency }} {{ number_format((float) $totalExpense, 2) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="alert {{ $resultType === 'profit' ? 'alert-success' : ($resultType === 'loss' ? 'alert-danger' : 'alert-secondary') }} text-end">
            <strong>
                {{ $resultType === 'profit' ? 'Utilidad del período' : ($resultType === 'loss' ? 'Pérdida del período' : 'Resultado del período') }}:
                {{ $company->currency }} {{ number_format((float) $result, 2) }}
            </strong>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-graph-up fs-1 text-muted"></i>
                <p class="text-muted mt-3 mb-0">No existen movimientos de ingresos o gastos para los filtros seleccionados.</p>
            </div>
        </div>
    @endif
</div>
@endsection
