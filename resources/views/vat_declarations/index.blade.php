@extends('layouts.app')
@section('title', 'Declaración de IVA')
@section('content')
<x-page-header title="Declaración de IVA" subtitle="Preparación, revisión y registro manual de presentación · {{ $company->name }}" icon="bi-percent" />

<div class="card mb-4"><div class="card-header"><h2 class="h6 mb-0">Preparar período</h2></div><div class="card-body">
    <form method="GET" action="{{ route('vat-declarations.preview') }}" class="row g-3 align-items-end">
        <div class="col-md-7"><label class="form-label">Período contable</label><select name="accounting_period_id" class="form-select" required><option value="">Seleccione…</option>@foreach($periods as $period)<option value="{{ $period->id }}">{{ $period->name }} · {{ $period->start_date->format('d/m/Y') }} al {{ $period->end_date->format('d/m/Y') }} · {{ $period->status->label() }}</option>@endforeach</select></div>
        <div class="col-md-5"><button class="btn btn-primary"><i class="bi bi-calculator me-1"></i> Calcular vista previa</button></div>
    </form>
</div></div>

<div class="card"><div class="card-header"><h2 class="h6 mb-0">Declaraciones preparadas</h2></div><x-table-container><table class="table table-hover align-middle mb-0"><thead><tr><th>Período</th><th>Rango</th><th>Estado</th><th>IVA por pagar</th><th>Crédito</th><th>Calculada</th><th></th></tr></thead><tbody>@forelse($declarations as $declaration)<tr><td>{{ $declaration->accountingPeriod->name }}</td><td>{{ $declaration->accountingPeriod->start_date->format('d/m/Y') }} al {{ $declaration->accountingPeriod->end_date->format('d/m/Y') }}</td><td><span class="badge text-bg-{{ $declaration->status->value==='FILED'?'success':($declaration->status->value==='READY_TO_FILE'?'primary':'secondary') }}">{{ $declaration->status->label() }}</span></td><td>Q {{ number_format((float)$declaration->vat_payable,2) }}</td><td>Q {{ number_format((float)$declaration->credit_balance,2) }}</td><td>{{ $declaration->calculated_at?->format('d/m/Y H:i') }}</td><td><a href="{{ route('vat-declarations.show',$declaration) }}" class="btn btn-sm btn-outline-primary">Abrir</a></td></tr>@empty<tr><td colspan="7"><x-empty-state title="Sin declaraciones" message="Seleccione un período para preparar el primer borrador." /></td></tr>@endforelse</tbody></table></x-table-container><div class="card-footer">{{ $declarations->links() }}</div></div>
@endsection
