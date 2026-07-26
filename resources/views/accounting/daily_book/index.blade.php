@extends('layouts.app')

@section('content')
<div class="container py-4">
    <x-page-header title="Libro Diario" subtitle="Pólizas contabilizadas de la empresa activa." icon="bi-book" />

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

    @include('accounting.daily_book._filters')

    @if($journalEntries->count())
        @foreach($journalEntries as $journalEntry)
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <span class="badge text-bg-success me-2">Contabilizada</span>
                            <strong>Póliza No. {{ $journalEntry->number }}</strong>
                            <span class="text-muted ms-2">{{ $journalEntry->entry_date->format('d/m/Y') }}</span>
                        </div>

                        <a href="{{ route('journal-entries.show', $journalEntry) }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-eye me-1"></i>Ver póliza original
                        </a>
                    </div>

                    <div class="mt-2">
                        <span class="text-muted">Concepto:</span>
                        {{ $journalEntry->description }}
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 150px;">Código</th>
                                    <th>Cuenta</th>
                                    <th>Descripción de línea</th>
                                    <th class="text-end" style="width: 160px;">Debe</th>
                                    <th class="text-end" style="width: 160px;">Haber</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($journalEntry->lines as $line)
                                    <tr>
                                        <td>{{ $line->account->code }}</td>
                                        <td>{{ $line->account->name }}</td>
                                        <td>{{ $line->description ?: '—' }}</td>
                                        <td class="text-end">Q {{ number_format((float) $line->debit, 2) }}</td>
                                        <td class="text-end">Q {{ number_format((float) $line->credit, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end">Total de la póliza</th>
                                    <th class="text-end">Q {{ number_format((float) $journalEntry->total_debit, 2) }}</th>
                                    <th class="text-end">Q {{ number_format((float) $journalEntry->total_credit, 2) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="card shadow-sm border-primary mb-4">
            <div class="card-body">
                <div class="row align-items-center g-3">
                    <div class="col-md-6">
                        <h2 class="h5 mb-1">Totales generales del reporte</h2>
                        <p class="text-muted mb-0">Incluyen todas las pólizas que coinciden con los filtros.</p>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <span class="text-muted d-block">Debe</span>
                        <strong class="fs-5">Q {{ number_format((float) $totalDebit, 2) }}</strong>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <span class="text-muted d-block">Haber</span>
                        <strong class="fs-5">Q {{ number_format((float) $totalCredit, 2) }}</strong>
                    </div>
                </div>

                @unless($isBalanced)
                    <div class="alert alert-warning mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Advertencia: los totales generales del Debe y el Haber no coinciden.
                    </div>
                @endunless
            </div>
        </div>

        <div class="mt-4">
            {{ $journalEntries->links('pagination::bootstrap-5') }}
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-journal-x fs-1 text-muted"></i>
                <p class="text-muted mt-3 mb-0">No existen pólizas contabilizadas que coincidan con los filtros seleccionados.</p>
            </div>
        </div>
    @endif
</div>
@endsection
