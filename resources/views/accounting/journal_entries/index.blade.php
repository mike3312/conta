@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Pólizas contables</h1>
            <p class="text-muted mb-0">Partidas de doble entrada de la empresa activa.</p>
        </div>

        <a href="{{ route('journal-entries.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nueva póliza
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('journal-entries.index') }}" class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Fecha inicial</label>
                    <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Fecha final</label>
                    <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                                {{ $status->label() }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Número o descripción</label>
                    <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Buscar póliza">
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">Filtrar</button>
                    <a href="{{ route('journal-entries.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            @if($journalEntries->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Fecha</th>
                                <th>Descripción</th>
                                <th>Referencia</th>
                                <th>Estado</th>
                                <th class="text-end">Debe</th>
                                <th class="text-end">Haber</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($journalEntries as $journalEntry)
                                <tr>
                                    <td>{{ $journalEntry->number ?? 'Borrador' }}</td>
                                    <td>{{ $journalEntry->entry_date->format('d/m/Y') }}</td>
                                    <td>{{ $journalEntry->description }}</td>
                                    <td>{{ $journalEntry->reference ?: '—' }}</td>
                                    <td>
                                        @php
                                            $badgeClass = match($journalEntry->status) {
                                                \App\Enums\JournalEntryStatus::DRAFT => 'text-bg-secondary',
                                                \App\Enums\JournalEntryStatus::POSTED => 'text-bg-success',
                                                \App\Enums\JournalEntryStatus::VOIDED => 'text-bg-danger',
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">{{ $journalEntry->status->label() }}</span>
                                    </td>
                                    <td class="text-end">Q {{ number_format((float) ($journalEntry->total_debit ?? 0), 2) }}</td>
                                    <td class="text-end">Q {{ number_format((float) ($journalEntry->total_credit ?? 0), 2) }}</td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('journal-entries.show', $journalEntry) }}" class="btn btn-sm btn-outline-secondary">Ver</a>

                                        @if($journalEntry->status === \App\Enums\JournalEntryStatus::DRAFT)
                                            <a href="{{ route('journal-entries.edit', $journalEntry) }}" class="btn btn-sm btn-outline-primary">Editar</a>

                                            <form action="{{ route('journal-entries.post', $journalEntry) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Deseas contabilizar esta póliza? Esta acción bloqueará su edición.');">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success">Contabilizar</button>
                                            </form>

                                            <form action="{{ route('journal-entries.destroy', $journalEntry) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Deseas eliminar este borrador?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                            </form>
                                        @elseif($journalEntry->status === \App\Enums\JournalEntryStatus::POSTED)
                                            <a href="{{ route('journal-entries.show', $journalEntry) }}#void-form" class="btn btn-sm btn-outline-danger">Anular</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    {{ $journalEntries->links('pagination::bootstrap-5') }}
                </div>
            @else
                <p class="text-muted mb-0">No hay pólizas que coincidan con los filtros.</p>
            @endif
        </div>
    </div>
</div>
@endsection
