@extends('layouts.app')
@section('title', 'Resultado de reclasificación FEL')
@section('content')
<x-page-header title="Reclasificación finalizada" subtitle="Empresa activa: {{ $company->name }}" icon="bi-check2-circle" />

@if($summary['failed'] > 0)
    <div class="alert alert-warning app-alert"><i class="bi bi-exclamation-triangle"></i><span>Algunos documentos no pudieron reclasificarse. Los demás fueron procesados correctamente.</span></div>
@elseif($summary['candidates'] === 0)
    <div class="alert alert-info app-alert"><i class="bi bi-info-circle"></i><span>No hay documentos para reclasificar.</span></div>
@else
    <div class="alert alert-success app-alert"><i class="bi bi-check2-circle"></i><span>Los documentos FEL fueron reclasificados correctamente.</span></div>
@endif

<div class="row g-3 mb-4">
    @foreach([
        ['Documentos revisados', $summary['processed']],
        ['Documentos actualizados', $summary['changed']],
        ['Compras detectadas', $summary['purchases']],
        ['Ventas detectadas', $summary['sales']],
        ['Desconocidos', $summary['unknown']],
        ['Combustibles', $summary['fuel']],
        ['Hospedajes', $summary['lodging']],
        ['Revisión fiscal requerida', $summary['tax_review_required']],
        ['Sin cambios', $summary['unchanged']],
        ['No procesados', $summary['failed']],
    ] as [$label, $value])
        <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="h4 mb-0 mt-2">{{ number_format($value) }}</div></div></div></div>
    @endforeach
</div>

<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('fel-documents.index') }}" class="btn btn-primary"><i class="bi bi-inbox me-1"></i>Volver a la Bandeja de revisión</a>
    <a href="{{ route('fel-documents.index', ['operation_type' => 'UNKNOWN']) }}" class="btn btn-outline-secondary">Ver documentos desconocidos</a>
</div>
@endsection
