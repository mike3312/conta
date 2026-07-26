@extends('layouts.app')

@section('title', 'Resumen financiero')
@section('breadcrumbs')<li class="breadcrumb-item active">Resumen</li>@endsection

@section('content')
@if(!$dashboard)
    <x-page-header title="Resumen financiero" subtitle="Configura una empresa para comenzar a administrar tu contabilidad." icon="bi-grid-1x2-fill" />
    <x-app-card><x-empty-state icon="bi-buildings" title="Aún no tienes una empresa activa" description="Crea tu primera empresa para habilitar el dashboard financiero."><x-slot:action><x-action-button :href="route('companies.create')" icon="bi-plus-lg">Crear empresa</x-action-button></x-slot:action></x-empty-state></x-app-card>
@else
    @php($currency = $dashboard['company']->currency ?: 'GTQ')
    <x-page-header title="Resumen financiero" :subtitle="'Información de '.$dashboard['company']->name" icon="bi-grid-1x2-fill">
        <x-slot:actions>
            <form method="GET" action="{{ route('dashboard') }}" class="d-flex flex-wrap gap-2 align-items-end">
                <div><label class="form-label mb-1" for="dashboardPeriod">Período</label><select id="dashboardPeriod" name="accounting_period_id" class="form-select form-select-sm" onchange="this.form.submit()"><option value="">Rango personalizado</option>@foreach($dashboard['periods'] as $period)<option value="{{ $period->id }}" @selected((int) $dashboard['filters']['accounting_period_id'] === $period->id)>{{ $period->name }}</option>@endforeach</select></div>
                <div><label class="form-label mb-1" for="dateFrom">Desde</label><input id="dateFrom" type="date" name="date_from" class="form-control form-control-sm" value="{{ $dashboard['filters']['date_from'] }}"></div>
                <div><label class="form-label mb-1" for="dateTo">Hasta</label><input id="dateTo" type="date" name="date_to" class="form-control form-control-sm" value="{{ $dashboard['filters']['date_to'] }}"></div>
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Actualizar</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3 mb-4">
        @foreach([
            ['label' => 'Ingresos', 'value' => $dashboard['metrics']['income'], 'icon' => 'bi-arrow-up-right', 'class' => 'metric-income'],
            ['label' => 'Gastos', 'value' => $dashboard['metrics']['expenses'], 'icon' => 'bi-arrow-down-right', 'class' => 'metric-expense'],
            ['label' => $dashboard['metrics']['net_result'] >= 0 ? 'Utilidad neta' : 'Pérdida neta', 'value' => abs($dashboard['metrics']['net_result']), 'icon' => 'bi-graph-up', 'class' => 'metric-profit'],
            ['label' => 'Activos totales', 'value' => $dashboard['metrics']['assets'], 'icon' => 'bi-bank', 'class' => 'metric-assets'],
        ] as $metric)
            <div class="col-12 col-sm-6 col-xl-3"><div class="app-card metric-card {{ $metric['class'] }}"><span class="metric-label">{{ $metric['label'] }}</span><strong class="metric-value">{{ $currency }} {{ number_format($metric['value'], 2) }}</strong><span class="metric-icon"><i class="bi {{ $metric['icon'] }}"></i></span></div></div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8"><x-app-card title="Ingresos y gastos" subtitle="Movimientos contabilizados agrupados por mes"><div class="chart-container"><canvas id="financialOverviewChart"></canvas></div><script type="application/json" id="financialOverviewData">@json($dashboard['chart'])</script></x-app-card></div>
        <div class="col-12 col-xl-4"><x-app-card title="Accesos rápidos" subtitle="Operaciones frecuentes"><div class="d-grid gap-2">
            <a class="quick-action" href="{{ route('journal-entries.create') }}"><i class="bi bi-plus-lg"></i><span>Nueva póliza</span></a>
            <a class="quick-action" href="{{ route('accounting.daily-book.index') }}"><i class="bi bi-book"></i><span>Libro Diario</span></a>
            <a class="quick-action" href="{{ route('accounting.general-ledger.index') }}"><i class="bi bi-journals"></i><span>Libro Mayor</span></a>
            <a class="quick-action" href="{{ route('accounting.income-statement.index') }}"><i class="bi bi-graph-up-arrow"></i><span>Estado de resultados</span></a>
            <a class="quick-action" href="{{ route('accounting.balance-sheet.index') }}"><i class="bi bi-bank"></i><span>Balance general</span></a>
        </div></x-app-card></div>
    </div>

    <x-table-container title="Actividad reciente" subtitle="Últimas pólizas contables de la empresa activa">
        @if($dashboard['recentEntries']->isEmpty())
            <x-empty-state icon="bi-journal-text" title="Sin pólizas recientes" description="Las pólizas que registres aparecerán aquí." />
        @else
            <table class="table table-hover align-middle"><thead><tr><th>Número</th><th>Fecha</th><th>Concepto</th><th class="text-end">Total</th><th>Estado</th></tr></thead><tbody>
            @foreach($dashboard['recentEntries'] as $entry)<tr><td><a href="{{ route('journal-entries.show', $entry) }}" class="fw-semibold">{{ $entry->number ? '#'.str_pad($entry->number, 6, '0', STR_PAD_LEFT) : 'Sin asignar' }}</a></td><td>{{ $entry->entry_date->format('d/m/Y') }}</td><td>{{ $entry->description }}</td><td class="text-end fw-semibold">{{ $currency }} {{ number_format((float) $entry->total_amount, 2) }}</td><td><x-status-badge :status="$entry->status" /></td></tr>@endforeach
            </tbody></table>
        @endif
    </x-table-container>
@endif
@endsection
