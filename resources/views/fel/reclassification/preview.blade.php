@extends('layouts.app')
@section('title', 'Vista previa de reclasificación FEL')
@section('content')
@php($labels = ['PURCHASE'=>'Compra','SALE'=>'Venta','UNKNOWN'=>'Desconocida','GENERAL_PURCHASE'=>'Compra general','GENERAL_SALE'=>'Venta general','FUEL'=>'Combustible','LODGING'=>'Hospedaje','UNCLASSIFIED'=>'Sin clasificar'])
<x-page-header title="Vista previa de reclasificación" subtitle="{{ $company->name }} · {{ $scope === 'unknown' ? 'Solo operaciones desconocidas' : 'Todos los documentos FEL' }}" icon="bi-search" />

<div class="alert alert-info"><i class="bi bi-shield-check me-1"></i>Esta vista previa no modificó ningún documento. Los estados de revisión existentes se conservarán.</div>
@if($summary['reviewed_included'] > 0)
    <div class="alert alert-warning"><strong>{{ number_format($summary['reviewed_included']) }}</strong> documentos revisados están incluidos. Solo se recalcularán sus campos de clasificación.</div>
@endif
@if($summary['failed'] > 0)
    <div class="alert alert-warning">No fue posible analizar {{ number_format($summary['failed']) }} documentos en la vista previa. Puede volver a intentarlo.</div>
@endif

<div class="row g-3 mb-4">
    @foreach([
        ['Documentos revisados', $summary['processed']],
        ['Cambiarían', $summary['changed']],
        ['Compras detectadas', $summary['purchases']],
        ['Ventas detectadas', $summary['sales']],
        ['Seguirían desconocidos', $summary['unknown']],
        ['Compras generales', $summary['general_purchases']],
        ['Ventas generales', $summary['general_sales']],
        ['Combustibles', $summary['fuel']],
        ['Hospedajes', $summary['lodging']],
        ['Revisión fiscal requerida', $summary['tax_review_required']],
    ] as [$label, $value])
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="h4 mb-0 mt-2">{{ number_format($value) }}</div></div></div></div>
    @endforeach
</div>

@if(count($summary['changes']) > 0)
    <x-table-container>
        <table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>UUID</th><th>Emisor</th><th>Receptor</th><th>Operación actual</th><th>Operación propuesta</th><th>Clasificación actual</th><th>Clasificación propuesta</th></tr></thead>
            <tbody>@foreach($summary['changes'] as $change)<tr>
                <td class="font-monospace">{{ $change['uuid'] }}</td><td>{{ $change['issuer'] }}</td><td>{{ $change['receiver'] }}</td>
                <td>{{ $labels[$change['current_operation']] ?? $change['current_operation'] }}</td><td>{{ $labels[$change['proposed_operation']] ?? $change['proposed_operation'] }}</td>
                <td>{{ $labels[$change['current_classification']] ?? $change['current_classification'] }}</td><td>{{ $labels[$change['proposed_classification']] ?? $change['proposed_classification'] }}</td>
            </tr>@endforeach</tbody>
        </table>
    </x-table-container>
    @if($summary['changes_truncated'])<p class="small text-muted mt-2">Se muestran los primeros 100 cambios.</p>@endif
@else
    <div class="alert alert-success">No se detectaron cambios de clasificación para este alcance.</div>
@endif

<form method="POST" action="{{ route('fel.reclassification.execute') }}" class="d-flex flex-wrap justify-content-end gap-2 mt-4" data-loading-form>
    @csrf
    <input type="hidden" name="scope" value="{{ $scope }}">
    <a href="{{ route('fel.reclassification.index') }}" class="btn btn-outline-secondary">Volver</a>
    <button type="submit" class="btn btn-primary" data-loading-button data-loading-text="Reclasificando documentos...">
        <span class="spinner-border spinner-border-sm me-2 d-none" aria-hidden="true" data-loading-spinner></span>
        <span data-loading-label>Confirmar reclasificación</span>
    </button>
</form>
@endsection
