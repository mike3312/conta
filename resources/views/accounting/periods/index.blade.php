@extends('layouts.app')

@section('title', 'Períodos contables')
@section('breadcrumbs')<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Resumen</a></li><li class="breadcrumb-item active">Períodos contables</li>@endsection

@section('content')
<x-page-header title="Períodos contables" subtitle="Administra los rangos y cierres de la empresa activa." icon="bi-calendar3">
    <x-slot:actions><x-action-button :href="route('accounting-periods.create')" icon="bi-plus-lg">Nuevo período</x-action-button></x-slot:actions>
</x-page-header>

<x-table-container>
    @if($periods->isEmpty())
        <x-empty-state icon="bi-calendar3" title="Sin períodos contables" description="Crea el primer período para comenzar a registrar pólizas." />
    @else
        <table class="table table-hover align-middle">
            <thead><tr><th>Nombre</th><th>Rango</th><th>Estado</th><th>Cierre</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
                @foreach($periods as $period)
                    <tr>
                        <td class="fw-semibold">{{ $period->name }}</td>
                        <td>{{ $period->start_date->format('d/m/Y') }} — {{ $period->end_date->format('d/m/Y') }}</td>
                        <td><x-status-badge :status="$period->status" /></td>
                        <td>
                            @if($period->status === \App\Enums\AccountingPeriodStatus::CLOSED)
                                <span class="d-block">{{ $period->closed_at?->format('d/m/Y H:i') }}</span>
                                <small class="text-muted">por {{ $period->closedBy?->name ?? 'Usuario no disponible' }}</small>
                            @else
                                <span class="text-muted">Pendiente</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if($period->status === \App\Enums\AccountingPeriodStatus::OPEN)
                                <a href="{{ route('accounting-periods.edit', $period) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#closePeriod{{ $period->id }}"><i class="bi bi-lock me-1"></i>Cerrar período</button>
                                <form action="{{ route('accounting-periods.destroy', $period) }}" method="POST" class="d-inline">@csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Deseas eliminar este período contable?')"><i class="bi bi-trash me-1"></i>Eliminar</button>
                                </form>
                            @else
                                <span class="text-muted small"><i class="bi bi-lock-fill me-1"></i>Sin modificaciones</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-table-container>

@foreach($periods->where('status', \App\Enums\AccountingPeriodStatus::OPEN) as $period)
    <div class="modal fade" id="closePeriod{{ $period->id }}" tabindex="-1" aria-labelledby="closePeriodLabel{{ $period->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="closePeriodLabel{{ $period->id }}">Cerrar {{ $period->name }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="alert alert-warning d-flex gap-2"><i class="bi bi-exclamation-triangle-fill"></i><span>Después de cerrar el período no se podrán crear, editar, eliminar ni contabilizar pólizas dentro de sus fechas.</span></div>
                <p class="mb-0">Se validará que no existan borradores, fechas fuera del rango ni pólizas contabilizadas descuadradas. Esta acción no genera ni elimina movimientos.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form action="{{ route('accounting-periods.close', $period) }}" method="POST">@csrf<button type="submit" class="btn btn-warning"><i class="bi bi-lock-fill me-1"></i>Confirmar cierre</button></form>
            </div>
        </div></div>
    </div>
@endforeach
@endsection
