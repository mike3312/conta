@extends('layouts.app')
@section('title', 'Documento fiscal')
@section('content')
<x-page-header title="{{ $document->document_type->label() }} {{ $document->series }} {{ $document->document_number }}" :subtitle="$bookTitle" icon="bi-receipt">
    <x-slot:actions>
        @if($document->status->value === 'ACTIVE')<x-action-button :href="route($routePrefix.'.edit',$document->id)" icon="bi-pencil" variant="outline-primary">Editar</x-action-button>@endif
        <x-action-button :href="route($routePrefix.'.index')" icon="bi-arrow-left" variant="outline-secondary">Volver</x-action-button>
    </x-slot:actions>
</x-page-header>

@if($document->document_type->value === 'CREDIT_NOTE')<div class="alert alert-info">Esta nota de crédito disminuye los totales del libro.</div>@elseif($document->document_type->value === 'DEBIT_NOTE')<div class="alert alert-warning">Esta nota de débito aumenta los totales del libro.</div>@endif
@if($document->status->value === 'VOIDED')<div class="alert alert-secondary"><strong>Documento anulado.</strong> Se conserva para consulta y no participa en los totales. @if($document->void_reason)<div class="mt-1">Motivo: {{ $document->void_reason }}</div>@endif</div>@endif

<div class="row g-4"><div class="col-lg-8"><div class="card"><div class="card-header"><h2 class="h6 mb-0">Información fiscal</h2></div><div class="card-body"><div class="row g-3">
@foreach([
    ['Fecha',$document->document_date->format('d/m/Y')],['Tipo',$document->document_type->label()],['Categoría',$document->tax_category->label()],['Estado',$document->status->label()],
    ['Serie',$document->series ?: '—'],['Número',$document->document_number ?: '—'],['UUID FEL',$document->authorization_uuid ?: '—'],['NIT '.$thirdPartyLabel,$document->third_party_tax_id ?: '—'],
    [$thirdPartyLabel,$document->third_party_name],['Dirección',$document->third_party_address ?: '—'],['Período',$document->accountingPeriod?->name ?: 'Sin asociar'],['Póliza',$document->journalEntry ? '#'.($document->journalEntry->number ?: 'Borrador') : 'Sin asociar'],
] as [$label,$value])<div class="col-md-6"><div class="small text-muted">{{ $label }}</div><div @class(['font-monospace'=>$label==='UUID FEL'])>{{ $value }}</div></div>@endforeach
</div></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-header"><h2 class="h6 mb-0">Importes</h2></div><div class="card-body">@foreach(['Base imponible'=>'taxable_amount','Monto exento'=>'exempt_amount','Monto no afecto'=>'non_taxable_amount','IVA'=>'vat_amount','Otros impuestos'=>'other_taxes_amount','Total'=>'total_amount'] as $label=>$field)<div class="d-flex justify-content-between py-2 border-bottom"><span>{{ $label }}</span><strong>Q {{ number_format((float)$document->{$field},2) }}</strong></div>@endforeach @if($direction->value==='PURCHASE')<div class="mt-3"><span class="badge text-bg-{{ $document->grants_tax_credit?'success':'secondary' }}">{{ $document->grants_tax_credit?'Genera crédito fiscal':'No genera crédito fiscal' }}</span></div>@endif</div></div></div></div>

@if($document->notes)<div class="card mt-4"><div class="card-header"><h2 class="h6 mb-0">Observaciones</h2></div><div class="card-body">{{ $document->notes }}</div></div>@endif

<div class="card mt-4"><div class="card-header"><h2 class="h6 mb-0">Trazabilidad de origen</h2></div><div class="card-body"><div class="row g-3">
<div class="col-md-4"><div class="small text-muted">Origen</div><div>{{ $document->source?->label() ?? 'Registro manual' }}</div></div>
<div class="col-md-4"><div class="small text-muted">Referencia</div><div class="font-monospace">{{ $document->source_reference ?: '—' }}</div></div>
<div class="col-md-4"><div class="small text-muted">Última sincronización</div><div>{{ data_get($document->source_metadata, 'synchronized_at') ? \Illuminate\Support\Carbon::parse(data_get($document->source_metadata, 'synchronized_at'))->format('d/m/Y H:i') : '—' }}</div></div>
@if($document->felDocument)
<div class="col-md-4"><div class="small text-muted">Nivel FEL</div><div>{{ data_get($document->source_metadata, 'source_detail_level', '—') }}</div></div>
<div class="col-md-4"><div class="small text-muted">Lote de importación</div><div>{{ $document->felDocument->importBatch?->original_filename ?: '—' }}</div></div>
<div class="col-md-4 d-flex align-items-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('fel-documents.show', $document->felDocument) }}">Ver documento FEL</a></div>
@endif
</div>@foreach(data_get($document->source_metadata, 'warnings', []) as $warning)<div class="alert alert-warning small py-2 mt-3 mb-0">{{ $warning }}</div>@endforeach</div></div>

@if($document->status->value === 'ACTIVE')
<div class="card border-danger mt-4"><div class="card-header"><h2 class="h6 mb-0 text-danger">Anular documento</h2></div><div class="card-body"><p class="text-muted">La anulación conserva todos los datos y excluye el documento de los totales activos.</p><form method="POST" action="{{ route($routePrefix.'.void',$document->id) }}" data-confirm="¿Desea anular este documento fiscal?">@csrf<div class="row g-2"><div class="col-md-9"><input name="void_reason" class="form-control" maxlength="1000" placeholder="Motivo de anulación (opcional)"></div><div class="col-md-3 d-grid"><button class="btn btn-outline-danger">Anular documento</button></div></div></form></div></div>
@endif
@endsection
