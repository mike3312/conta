@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <h1 class="h3 mb-1">Libro Mayor</h1>
        <p class="text-muted mb-0">Movimientos y saldos acumulados por cuenta de la empresa activa.</p>
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

    @include('accounting.general_ledger._filters')

    @if($ledgerAccounts->count())
        @foreach($ledgerAccounts as $ledger)
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h2 class="h5 mb-1">
                                {{ $ledger['account']->code }} - {{ $ledger['account']->name }}
                            </h2>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge text-bg-light border">{{ $ledger['account']->account_type->label() }}</span>
                                <span class="badge text-bg-secondary">Naturaleza {{ $ledger['account']->nature->label() }}</span>
                                <span class="badge {{ $ledger['is_normal_balance'] ? 'text-bg-success' : 'text-bg-warning' }}">
                                    {{ $ledger['is_normal_balance'] ? 'Saldo normal' : 'Saldo contrario a su naturaleza' }}
                                </span>
                            </div>
                        </div>

                        <div class="text-end">
                            <span class="text-muted d-block">Saldo final</span>
                            <strong class="fs-5">
                                {{ $currency }} {{ number_format((float) $ledger['final_balance'], 2) }}
                                <span class="badge {{ $ledger['final_balance_type'] === 'Acreedor' ? 'text-bg-info' : 'text-bg-primary' }}">
                                    {{ $ledger['final_balance_type'] }}
                                </span>
                            </strong>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-3">
                            <span class="text-muted d-block">Saldo anterior</span>
                            <strong>{{ $currency }} {{ number_format((float) $ledger['previous_balance'], 2) }} {{ $ledger['previous_balance_type'] }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted d-block">Debe del rango</span>
                            <strong>{{ $currency }} {{ number_format((float) $ledger['total_debit'], 2) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted d-block">Haber del rango</span>
                            <strong>{{ $currency }} {{ number_format((float) $ledger['total_credit'], 2) }}</strong>
                        </div>
                        <div class="col-md-3">
                            <span class="text-muted d-block">Saldo final</span>
                            <strong>{{ $currency }} {{ number_format((float) $ledger['final_balance'], 2) }} {{ $ledger['final_balance_type'] }}</strong>
                        </div>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 120px;">Fecha</th>
                                    <th style="width: 110px;">Póliza</th>
                                    <th>Concepto</th>
                                    <th>Descripción de línea</th>
                                    <th class="text-end" style="width: 140px;">Debe</th>
                                    <th class="text-end" style="width: 140px;">Haber</th>
                                    <th class="text-end" style="width: 190px;">Saldo acumulado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="table-secondary">
                                    <td colspan="4"><strong>Saldo anterior</strong></td>
                                    <td class="text-end">—</td>
                                    <td class="text-end">—</td>
                                    <td class="text-end">
                                        <strong>{{ $currency }} {{ number_format((float) $ledger['previous_balance'], 2) }} {{ $ledger['previous_balance_type'] }}</strong>
                                    </td>
                                </tr>

                                @forelse($ledger['movements'] as $movement)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($movement['entry_date'])->format('d/m/Y') }}</td>
                                        <td>
                                            <a href="{{ route('journal-entries.show', $movement['entry_id']) }}">
                                                {{ $movement['number'] }}
                                            </a>
                                        </td>
                                        <td>{{ $movement['entry_description'] }}</td>
                                        <td>{{ $movement['line_description'] ?: '—' }}</td>
                                        <td class="text-end">{{ $currency }} {{ number_format((float) $movement['debit'], 2) }}</td>
                                        <td class="text-end">{{ $currency }} {{ number_format((float) $movement['credit'], 2) }}</td>
                                        <td class="text-end">
                                            <strong>{{ $currency }} {{ number_format((float) $movement['balance'], 2) }}</strong>
                                            <span class="badge {{ $movement['balance_type'] === 'Acreedor' ? 'text-bg-info' : 'text-bg-primary' }}">
                                                {{ $movement['balance_type'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            Esta cuenta no tiene movimientos dentro del rango seleccionado.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="4" class="text-end">Totales y saldo final</th>
                                    <th class="text-end">{{ $currency }} {{ number_format((float) $ledger['total_debit'], 2) }}</th>
                                    <th class="text-end">{{ $currency }} {{ number_format((float) $ledger['total_credit'], 2) }}</th>
                                    <th class="text-end">
                                        {{ $currency }} {{ number_format((float) $ledger['final_balance'], 2) }} {{ $ledger['final_balance_type'] }}
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="mt-4">
            {{ $ledgerAccounts->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-journals fs-1 text-muted"></i>
                <p class="text-muted mt-3 mb-0">No existen cuentas con movimientos que coincidan con los filtros seleccionados.</p>
            </div>
        </div>
    @endif
</div>
@endsection
