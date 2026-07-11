@extends('layouts.app')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Períodos contables</h1>
            <p class="text-muted mb-0">Períodos contables de la empresa activa.</p>
        </div>

        <a href="{{ route('accounting-periods.create') }}" class="btn btn-primary">
            Nuevo período
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            @if($periods->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Fecha inicial</th>
                                <th>Fecha final</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($periods as $period)
                                <tr>
                                    <td>{{ $period->name }}</td>
                                    <td>{{ $period->start_date->format('d/m/Y') }}</td>
                                    <td>{{ $period->end_date->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge {{ $period->status === \App\Enums\AccountingPeriodStatus::OPEN ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $period->status->label() }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('accounting-periods.edit', $period) }}" class="btn btn-sm btn-outline-primary">
                                            Editar
                                        </a>

                                        <form action="{{ route('accounting-periods.destroy', $period) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Deseas eliminar este período contable?')">
                                                Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted mb-0">Aún no hay períodos contables registrados.</p>
            @endif
        </div>
    </div>

</div>
@endsection
