@extends('layouts.app')

@section('content')
<style>
@media print {
    body > .d-flex > .bg-dark,
    nav.navbar,
    footer,
    .balance-sheet-no-print {
        display: none !important;
    }

    body,
    main.container-fluid,
    .balance-sheet-report {
        background: #fff !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .balance-sheet-report .card {
        border: 0 !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
}
</style>

<div class="container py-4 balance-sheet-report">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Balance General</h1>
            <h2 class="h5 mb-1">{{ $company->name }}</h2>
            <p class="text-muted mb-0">
                Balance General al {{ \Carbon\Carbon::parse($filters['cutoff_date'])->format('d/m/Y') }}
                · Moneda: {{ $company->currency }}
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 balance-sheet-no-print">
            @if($isBalanced)
                <span class="badge text-bg-success fs-6 px-3 py-2">Balance cuadrado</span>
            @else
                <span class="badge text-bg-danger fs-6 px-3 py-2">Balance descuadrado</span>
            @endif

            <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>Imprimir
            </button>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger balance-sheet-no-print">
            <strong>No se pudieron aplicar los filtros.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @unless($isBalanced)
        <div class="alert alert-danger">
            <strong>Balance descuadrado.</strong>
            La diferencia es {{ $company->currency }} {{ number_format((float) $difference, 2) }}
            a favor de {{ $differenceSide }}. No se realizaron ajustes automáticos.
        </div>
    @endunless

    @include('accounting.balance_sheet._filters')

    <div class="row g-3 mb-4">
        <div class="col-md">
            <div class="card shadow-sm h-100 border-primary"><div class="card-body">
                <span class="text-muted d-block">Total Activos</span>
                <strong class="fs-5">{{ $company->currency }} {{ number_format((float) $totalAssets, 2) }}</strong>
                @if($assetsAreContrary)<span class="badge text-bg-warning d-block mt-2">Saldo contrario</span>@endif
            </div></div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm h-100 border-danger"><div class="card-body">
                <span class="text-muted d-block">Total Pasivos</span>
                <strong class="fs-5">{{ $company->currency }} {{ number_format((float) $totalLiabilities, 2) }}</strong>
                @if($liabilitiesAreContrary)<span class="badge text-bg-warning d-block mt-2">Saldo contrario</span>@endif
            </div></div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm h-100 border-success"><div class="card-body">
                <span class="text-muted d-block">Patrimonio total</span>
                <strong class="fs-5">{{ $company->currency }} {{ number_format((float) $totalEquity, 2) }}</strong>
                @if($equityIsContrary)<span class="badge text-bg-warning d-block mt-2">Saldo contrario</span>@endif
            </div></div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm h-100 border-info"><div class="card-body">
                <span class="text-muted d-block">Resultado pendiente</span>
                <strong class="fs-5">{{ $company->currency }} {{ number_format((float) $pendingResult, 2) }}</strong>
                <span class="d-block small">{{ $pendingResultType === 'profit' ? 'Utilidad' : ($pendingResultType === 'loss' ? 'Pérdida' : 'Sin resultado') }}</span>
            </div></div>
        </div>
        <div class="col-md">
            <div class="card shadow-sm h-100 {{ $isBalanced ? 'border-success' : 'border-danger' }}"><div class="card-body">
                <span class="text-muted d-block">Diferencia</span>
                <strong class="fs-5">{{ $company->currency }} {{ number_format((float) $difference, 2) }}</strong>
            </div></div>
        </div>
    </div>

    @if($hasInformation)
        @foreach([
            ['title' => 'Activos', 'section' => $assetSection, 'total' => $totalAssets, 'class' => 'primary'],
            ['title' => 'Pasivos', 'section' => $liabilitySection, 'total' => $totalLiabilities, 'class' => 'danger'],
        ] as $reportSection)
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-{{ $reportSection['class'] }} text-white"><strong>{{ $reportSection['title'] }}</strong></div>
                <div class="card-body p-0"><div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Código</th><th>Cuenta</th><th class="text-end">Saldo</th></tr></thead>
                        <tbody>
                            @include('accounting.balance_sheet._account_rows', [
                                'nodes' => $reportSection['section'],
                                'level' => 0,
                                'currency' => $company->currency,
                            ])
                        </tbody>
                        <tfoot class="table-{{ $reportSection['class'] }}"><tr>
                            <th colspan="2" class="text-end">Total {{ $reportSection['title'] }}</th>
                            <th class="text-end">{{ $company->currency }} {{ number_format((float) $reportSection['total'], 2) }}</th>
                        </tr></tfoot>
                    </table>
                </div></div>
            </div>
        @endforeach

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-success text-white"><strong>Patrimonio</strong></div>
            <div class="card-body p-0"><div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Código</th><th>Cuenta</th><th class="text-end">Saldo</th></tr></thead>
                    <tbody>
                        @include('accounting.balance_sheet._account_rows', [
                            'nodes' => $equitySection,
                            'level' => 0,
                            'currency' => $company->currency,
                        ])
                        <tr class="table-info">
                            <td>—</td>
                            <td>
                                <strong>Resultado acumulado pendiente de cierre</strong>
                                <span class="badge {{ $pendingResultType === 'loss' ? 'text-bg-danger' : 'text-bg-success' }} ms-1">
                                    {{ $pendingResultType === 'profit' ? 'Utilidad' : ($pendingResultType === 'loss' ? 'Pérdida' : 'Sin resultado') }}
                                </span>
                            </td>
                            <td class="text-end">
                                {{ $company->currency }} {{ number_format((float) $pendingResult, 2) }}
                                @if($pendingResultType === 'loss')<span class="text-danger">(disminuye)</span>@endif
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="table-success"><tr>
                        <th colspan="2" class="text-end">Total Patrimonio</th>
                        <th class="text-end">{{ $company->currency }} {{ number_format((float) $totalEquity, 2) }}</th>
                    </tr></tfoot>
                </table>
            </div></div>
        </div>

        <div class="card shadow-sm border-dark">
            <div class="card-header bg-dark text-white"><strong>Comprobación de la ecuación contable</strong></div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-md-4"><span class="text-muted d-block">Activos</span><strong>{{ $company->currency }} {{ number_format((float) $totalAssets, 2) }}</strong></div>
                    <div class="col-md-4"><span class="text-muted d-block">Pasivos + Patrimonio</span><strong>{{ $company->currency }} {{ number_format((float) $liabilitiesAndEquity, 2) }}</strong></div>
                    <div class="col-md-4"><span class="text-muted d-block">Diferencia</span><strong>{{ $company->currency }} {{ number_format((float) $difference, 2) }}</strong></div>
                </div>
                <div class="text-center mt-3">
                    <span class="badge {{ $isBalanced ? 'text-bg-success' : 'text-bg-danger' }} fs-6">
                        {{ $isBalanced ? 'Balance cuadrado' : 'Balance descuadrado' }}
                    </span>
                </div>
            </div>
        </div>
    @else
        <div class="card shadow-sm"><div class="card-body text-center py-5">
            <i class="bi bi-bank fs-1 text-muted"></i>
            <p class="text-muted mt-3 mb-0">No existen movimientos contables hasta la fecha de corte seleccionada.</p>
        </div></div>
    @endif
</div>
@endsection
