@extends('layouts.app')
@section('title', 'Reclasificar documentos FEL')
@section('content')
<x-page-header title="Reclasificar documentos FEL" subtitle="Se aplicarán el NIT actual y las reglas de clasificación vigentes." icon="bi-arrow-repeat" />

<div class="card mx-auto" style="max-width: 820px">
    <div class="card-body p-4 p-md-5">
        <div class="row g-3 mb-4">
            <div class="col-md-6"><div class="p-3 rounded bg-light"><div class="small text-muted">Empresa activa</div><strong>{{ $company->name }}</strong></div></div>
            <div class="col-md-6"><div class="p-3 rounded bg-light"><div class="small text-muted">NIT actual</div><strong>{{ $maskedTaxId }}</strong></div></div>
        </div>

        @if(blank($company->tax_id))
            <div class="alert alert-warning app-alert">
                <i class="bi bi-exclamation-triangle"></i>
                <div>
                    <div>No es posible reclasificar los documentos porque la empresa activa no tiene un NIT configurado.</div>
                    <a href="{{ route('companies.edit', $company) }}" class="btn btn-sm btn-warning mt-3">Editar empresa</a>
                </div>
            </div>
        @else
            <p>Se volverán a analizar los documentos FEL de la empresa activa utilizando el NIT actual y las reglas de clasificación vigentes.</p>
            <form method="POST" action="{{ route('fel.reclassification.preview') }}">
                @csrf
                <fieldset class="mb-4">
                    <legend class="h6">Alcance</legend>
                    <label class="d-flex gap-3 border rounded p-3 mb-2">
                        <input class="form-check-input" type="radio" name="scope" value="unknown" checked>
                        <span><strong>Solo documentos con operación desconocida</strong><small class="d-block text-muted">Opción recomendada · {{ number_format($unknownCount) }} documentos.</small></span>
                    </label>
                    <label class="d-flex gap-3 border rounded p-3">
                        <input class="form-check-input" type="radio" name="scope" value="all">
                        <span><strong>Todos los documentos FEL</strong><small class="d-block text-muted">{{ number_format($documentCount) }} documentos.</small></span>
                    </label>
                </fieldset>

                @if($reviewedCount > 0)
                    <div class="alert alert-warning"><i class="bi bi-shield-check me-1"></i>Hay {{ number_format($reviewedCount) }} documentos con revisión humana. Sus estados, observaciones y decisiones se conservarán.</div>
                @endif
                <div class="alert alert-info"><i class="bi bi-info-circle me-1"></i>La reclasificación no modifica los documentos originales, sus importes, impuestos ni estados de revisión.</div>
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <a href="{{ route('fel-documents.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">Revisar cambios</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
