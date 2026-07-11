@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">Póliza {{ $journalEntry->number ?? 'en borrador' }}</h1>
            <p class="text-muted mb-0">{{ $journalEntry->description }}</p>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('journal-entries.index') }}" class="btn btn-outline-secondary">Volver</a>

            @if($journalEntry->status === \App\Enums\JournalEntryStatus::DRAFT)
                <a href="{{ route('journal-entries.edit', $journalEntry) }}" class="btn btn-outline-primary">Editar</a>
                <form action="{{ route('journal-entries.post', $journalEntry) }}" method="POST" onsubmit="return confirm('¿Deseas contabilizar esta póliza? Esta acción bloqueará su edición.');">
                    @csrf
                    <button type="submit" class="btn btn-success">Contabilizar</button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><span class="text-muted d-block">Estado</span><strong>{{ $journalEntry->status->label() }}</strong></div>
                <div class="col-md-3"><span class="text-muted d-block">Fecha</span><strong>{{ $journalEntry->entry_date->format('d/m/Y') }}</strong></div>
                <div class="col-md-3"><span class="text-muted d-block">Período</span><strong>{{ $journalEntry->accountingPeriod->name }}</strong></div>
                <div class="col-md-3"><span class="text-muted d-block">Referencia</span><strong>{{ $journalEntry->reference ?: '—' }}</strong></div>
                <div class="col-md-3"><span class="text-muted d-block">Creada por</span><strong>{{ $journalEntry->creator->name }}</strong></div>

                @if($journalEntry->posted_at)
                    <div class="col-md-3"><span class="text-muted d-block">Contabilizada por</span><strong>{{ $journalEntry->postedBy?->name ?? '—' }}</strong></div>
                    <div class="col-md-3"><span class="text-muted d-block">Fecha de contabilización</span><strong>{{ $journalEntry->posted_at->format('d/m/Y H:i') }}</strong></div>
                @endif

                @if($journalEntry->voided_at)
                    <div class="col-md-3"><span class="text-muted d-block">Anulada por</span><strong>{{ $journalEntry->voidedBy?->name ?? '—' }}</strong></div>
                    <div class="col-md-3"><span class="text-muted d-block">Fecha de anulación</span><strong>{{ $journalEntry->voided_at->format('d/m/Y H:i') }}</strong></div>
                    <div class="col-12"><span class="text-muted d-block">Motivo de anulación</span><strong>{{ $journalEntry->void_reason }}</strong></div>
                @endif
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><strong>Líneas contables</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Cuenta</th><th>Descripción</th><th class="text-end">Debe</th><th class="text-end">Haber</th></tr></thead>
                    <tbody>
                        @foreach($journalEntry->lines as $line)
                            <tr>
                                <td><strong>{{ $line->account->code }}</strong> - {{ $line->account->name }}</td>
                                <td>{{ $line->description ?: '—' }}</td>
                                <td class="text-end">Q {{ number_format((float) $line->debit, 2) }}</td>
                                <td class="text-end">Q {{ number_format((float) $line->credit, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th colspan="2" class="text-end">Totales</th>
                            <th class="text-end">Q {{ number_format((float) $journalEntry->lines->sum('debit'), 2) }}</th>
                            <th class="text-end">Q {{ number_format((float) $journalEntry->lines->sum('credit'), 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if($journalEntry->status === \App\Enums\JournalEntryStatus::POSTED)
        <div class="card border-danger shadow-sm" id="void-form">
            <div class="card-header bg-danger text-white"><strong>Anular póliza</strong></div>
            <div class="card-body">
                <p class="text-muted">La póliza y su número se conservarán. Esta acción no puede revertirse.</p>
                <form action="{{ route('journal-entries.void', $journalEntry) }}" method="POST" onsubmit="return confirm('¿Deseas anular esta póliza?');">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Motivo de anulación</label>
                        <textarea name="void_reason" class="form-control" rows="3" maxlength="1000" required>{{ old('void_reason') }}</textarea>
                        @error('void_reason')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-danger">Anular póliza</button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
