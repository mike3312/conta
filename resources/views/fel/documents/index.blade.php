@extends('layouts.app')
@section('title', 'Bandeja de revisión FEL')
@section('content')
@php($labels=['PURCHASE'=>'Compra','SALE'=>'Venta','UNKNOWN'=>'Desconocida','GENERAL_PURCHASE'=>'Compra general','GENERAL_SALE'=>'Venta general','FUEL'=>'Combustible','LODGING'=>'Hospedaje','UNCLASSIFIED'=>'Sin clasificar','PENDING'=>'Pendiente','OBSERVED'=>'Observado','APPROVED'=>'Aprobado','REJECTED'=>'Rechazado','ACTIVE'=>'Activo','VOIDED'=>'Anulado','FULL_DETAIL'=>'Detalle completo','SUMMARY'=>'Solo resumen'])
<x-page-header title="Bandeja de revisión FEL" subtitle="Documentos pendientes de decisión humana · Empresa activa: {{ $activeCompanyName }}" icon="bi-inbox"><x-slot:actions><x-action-button :href="route('fel-imports.create')" icon="bi-cloud-arrow-up">Importar documentos</x-action-button><x-action-button :href="route('fel.reclassification.index')" icon="bi-arrow-repeat" variant="outline-primary">Reclasificar documentos FEL</x-action-button></x-slot:actions></x-page-header>

@if(session('fel_bulk_review_result'))
@php($bulk=session('fel_bulk_review_result'))
<div class="alert alert-info"><strong>Procesamiento completado.</strong><div class="mt-1">{{ $bulk['processed'] }} procesados · {{ $bulk['skipped'] }} omitidos · {{ $bulk['errors'] }} con error.</div><button class="btn btn-sm btn-link px-0" type="button" data-bs-toggle="collapse" data-bs-target="#bulkDetails">Ver detalles</button><div class="collapse" id="bulkDetails"><ul class="mb-0">@foreach($bulk['details'] as $detail)<li>Documento #{{ $detail['id'] }}: {{ $detail['message'] }}</li>@endforeach</ul><small class="text-muted">Lote de auditoría: {{ $bulk['batch_uuid'] }}</small></div></div>
@endif

<div class="card shadow-sm mb-4"><div class="card-body"><form class="row g-2">
    <div class="col-md-2"><label class="form-label">Desde</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div><div class="col-md-2"><label class="form-label">Hasta</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Operación</label><select name="operation_type" class="form-select"><option value="">Todas</option>@foreach($operationTypes as $item)<option value="{{ $item->value }}" @selected(request('operation_type')===$item->value)>{{ $labels[$item->value]??$item->value }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Clasificación</label><select name="classification" class="form-select"><option value="">Todas</option>@foreach($classifications as $item)<option value="{{ $item->value }}" @selected(request('classification')===$item->value)>{{ $labels[$item->value]??$item->value }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Estado interno</label><select name="status" class="form-select"><option value="">Todos</option>@foreach($statuses as $item)<option value="{{ $item->value }}" @selected(request('status')===$item->value)>{{ $labels[$item->value]??$item->value }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Estado fiscal</label><select name="fiscal_status" class="form-select"><option value="">Todos</option>@foreach($fiscalStatuses as $item)<option value="{{ $item->value }}" @selected(request('fiscal_status')===$item->value)>{{ $labels[$item->value]??$item->value }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Origen</label><select name="source_type" class="form-select"><option value="">Todos</option>@foreach($sourceTypes as $item)<option value="{{ $item->value }}" @selected(request('source_type')===$item->value)>{{ $item->value }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Nivel</label><select name="data_level" class="form-select"><option value="">Todos</option>@foreach($dataLevels as $item)<option value="{{ $item->value }}" @selected(request('data_level')===$item->value)>{{ $labels[$item->value]??$item->value }}</option>@endforeach</select></div>
    <div class="col-md-2"><label class="form-label">Tipo DTE</label><input name="dte_type" value="{{ request('dte_type') }}" class="form-control"></div><div class="col-md-2"><label class="form-label">UUID</label><input name="uuid" value="{{ request('uuid') }}" class="form-control"></div><div class="col-md-2"><label class="form-label">NIT</label><input name="nit" value="{{ request('nit') }}" class="form-control"></div><div class="col-md-2"><label class="form-label">Nombre</label><input name="name" value="{{ request('name') }}" class="form-control"></div>
    <div class="col-md-2"><label class="form-label">Régimen emisor</label><input name="issuer_tax_regime" value="{{ request('issuer_tax_regime') }}" class="form-control"></div><div class="col-md-2"><label class="form-label">Lote</label><input type="number" min="1" name="fel_import_batch_id" value="{{ request('fel_import_batch_id') }}" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Período contable</label><select name="accounting_period_id" class="form-select"><option value="">Todos</option>@foreach($periods as $period)<option value="{{ $period->id }}" @selected((string)request('accounting_period_id')===(string)$period->id)>{{ $period->name }} · {{ $period->start_date->format('d/m/Y') }} al {{ $period->end_date->format('d/m/Y') }}</option>@endforeach</select></div>
    <div class="col-12 d-flex gap-2"><button class="btn btn-primary">Aplicar filtros</button><a href="{{ route('fel-documents.index') }}" class="btn btn-outline-secondary">Limpiar</a></div>
</form></div></div>

@can('bulkReview', [App\Models\FelDocument::class, $activeCompany])
<form method="POST" action="{{ route('fel-documents.bulk-review') }}" id="bulkReviewForm">@csrf
    <input type="hidden" name="action" id="bulkAction">
    @foreach($filters as $key=>$value)@if($value!==null && $value!=='')<input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">@endif @endforeach
    <div class="card border-primary mb-3 d-none" id="bulkBar"><div class="card-body py-2 d-flex flex-wrap align-items-center gap-2"><strong><span id="selectedCount">0</span> seleccionados</strong><button type="button" class="btn btn-sm btn-success bulk-trigger" data-action="APPROVED">Aprobar</button><button type="button" class="btn btn-sm btn-warning bulk-trigger" data-action="OBSERVED">Observar</button><button type="button" class="btn btn-sm btn-danger bulk-trigger" data-action="REJECTED">Rechazar</button><button type="button" class="btn btn-sm btn-outline-secondary" id="clearSelection">Limpiar selección</button></div></div>
@endcan

<x-table-container><table class="table table-hover align-middle mb-0 small"><thead><tr>@can('bulkReview', [App\Models\FelDocument::class, $activeCompany])<th><input class="form-check-input" type="checkbox" id="selectVisible" aria-label="Seleccionar documentos elegibles visibles"></th>@endcan<th>Fecha</th><th>DTE / UUID</th><th>Operación</th><th>Emisor / receptor</th><th>Clasificación</th><th>Origen</th><th>IVA</th><th>Otros</th><th>Total</th><th>Estados</th><th></th></tr></thead><tbody>
@forelse($documents as $document)
@php($eligible=$document->fiscal_status->value!=='VOIDED' && $document->fiscalDocument?->accountingPeriod?->status?->value!=='closed')
<tr>@can('bulkReview', [App\Models\FelDocument::class, $activeCompany])<td><input class="form-check-input document-select" type="checkbox" name="document_ids[]" value="{{ $document->id }}" @disabled(!$eligible) aria-label="Seleccionar documento {{ $document->authorization_uuid }}">@if(!$eligible)<i class="bi bi-lock text-muted ms-1" title="Documento no elegible"></i>@endif</td>@endcan<td>{{ $document->issued_at->format('d/m/Y') }}</td><td><strong>{{ $document->dte_type }}</strong><div class="font-monospace text-muted">{{ Str::limit($document->authorization_uuid,22) }}</div></td><td><span class="badge text-bg-info">{{ $labels[$document->operation_type->value] }}</span></td><td><div>{{ $document->issuer_name }}</div><small class="text-muted">a {{ $document->receiver_name }}</small></td><td><span class="badge text-bg-{{ $document->classification->value==='FUEL'?'warning':'light' }}">{{ $labels[$document->classification->value] }}</span>@if($document->requires_tax_review)<div class="text-warning"><i class="bi bi-exclamation-triangle"></i> Revisión fiscal</div>@endif</td><td>{{ $document->source_type->value }}<br><small>{{ $labels[$document->data_level->value] }}</small></td><td>{{ number_format((float)$document->tax_total,2) }}</td><td>{{ number_format((float)$document->other_tax_total,2) }}</td><td>{{ $document->currency }} {{ number_format((float)$document->grand_total,2) }}</td><td><span class="badge text-bg-secondary">{{ $labels[$document->fiscal_status->value]??$document->fiscal_status->value }}</span><br><span class="badge text-bg-primary mt-1">{{ $labels[$document->status->value] }}</span></td><td><a href="{{ route('fel-documents.show',$document) }}" class="btn btn-sm btn-outline-primary">Revisar</a></td></tr>
@empty<tr><td colspan="12"><x-empty-state title="Sin documentos" message="No hay documentos que coincidan con los filtros." /></td></tr>@endforelse
</tbody></table></x-table-container>

@can('bulkReview', [App\Models\FelDocument::class, $activeCompany])
<div class="modal fade" id="bulkConfirmModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title fs-5" id="bulkModalTitle">Confirmar acción</h2><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><p id="bulkModalMessage"></p><p class="text-muted">Esta acción puede afectar los documentos disponibles para la preparación de IVA.</p><div id="bulkReasonGroup"><label class="form-label">Motivo</label><textarea name="reason" id="bulkReason" maxlength="2000" class="form-control"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" id="bulkSubmit">Procesar documentos</button></div></div></div></div>
</form>
@endcan
<div class="mt-3">{{ $documents->links() }}</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const checks = [...document.querySelectorAll('.document-select:not(:disabled)')];
    const master = document.getElementById('selectVisible');
    const bar = document.getElementById('bulkBar');
    const count = document.getElementById('selectedCount');
    if (!master || !bar || !count) return;
    const update = () => {
        const selected = checks.filter(item => item.checked).length;
        count.textContent = selected;
        bar.classList.toggle('d-none', selected === 0);
        master.checked = selected > 0 && selected === checks.length;
        master.indeterminate = selected > 0 && selected < checks.length;
    };
    master.addEventListener('change', () => { checks.forEach(item => item.checked = master.checked); update(); });
    checks.forEach(item => item.addEventListener('change', update));
    document.getElementById('clearSelection')?.addEventListener('click', () => { checks.forEach(item => item.checked = false); update(); });
    document.querySelectorAll('.bulk-trigger').forEach(button => button.addEventListener('click', () => {
        const selected = checks.filter(item => item.checked).length;
        if (!selected) return;
        const action = button.dataset.action;
        const verbs = {APPROVED: ['aprobar', 'Aprobar documentos'], OBSERVED: ['observar', 'Observar documentos'], REJECTED: ['rechazar', 'Rechazar documentos']};
        document.getElementById('bulkAction').value = action;
        document.getElementById('bulkModalTitle').textContent = verbs[action][1];
        document.getElementById('bulkModalMessage').textContent = `Está a punto de ${verbs[action][0]} ${selected} documentos FEL.`;
        const reason = document.getElementById('bulkReason');
        reason.required = action !== 'APPROVED';
        document.getElementById('bulkReasonGroup').classList.toggle('d-none', action === 'APPROVED');
        document.getElementById('bulkSubmit').textContent = verbs[action][1];
        bootstrap.Modal.getOrCreateInstance(document.getElementById('bulkConfirmModal')).show();
    }));
});
</script>
@endpush
