@extends('layouts.app')
@section('title', $bookTitle)
@section('content')
<x-page-header :title="$bookTitle" subtitle="Registro fiscal independiente · Empresa activa: {{ $company->name }}" icon="bi-journal-check">
    <x-slot:actions>
        <x-action-button :href="route($routePrefix.'.export.pdf', request()->query())" icon="bi-file-earmark-pdf" variant="outline-danger">Exportar PDF</x-action-button>
        <x-action-button :href="route($routePrefix.'.export.excel', request()->query())" icon="bi-file-earmark-excel" variant="outline-success">Exportar Excel</x-action-button>
        <x-action-button :href="route($routePrefix.'.create')" icon="bi-plus-lg">Registrar {{ $documentLabel }}</x-action-button>
    </x-slot:actions>
</x-page-header>

@include('fiscal_documents._filters')

<div class="row g-3 mb-4">
    @foreach([
        ['Documentos activos', $report['count']], ['Base imponible', 'Q '.number_format((float) $report['totals']['taxable_amount'], 2)],
        [$direction->value === 'PURCHASE' ? 'Monto exento' : 'Ventas exentas', 'Q '.number_format((float) $report['totals']['exempt_amount'], 2)],
        [$direction->value === 'PURCHASE' ? 'IVA registrado' : 'IVA débito registrado', 'Q '.number_format((float) $report['totals']['vat_amount'], 2)],
        ['Otros impuestos', 'Q '.number_format((float) $report['totals']['other_taxes_amount'], 2)],
        [$direction->value === 'PURCHASE' ? 'Total de compras' : 'Total de ventas', 'Q '.number_format((float) $report['totals']['total_amount'], 2)],
    ] as [$label, $value])<div class="col-6 col-lg-2"><div class="card h-100"><div class="card-body"><div class="small text-muted">{{ $label }}</div><strong class="d-block mt-2">{{ $value }}</strong></div></div></div>@endforeach
</div>

@if($direction->value === 'PURCHASE')
<div class="card mb-4"><div class="card-header"><h2 class="h6 mb-0">Subtotales por categoría</h2></div><div class="card-body"><div class="row g-3">@foreach($report['categoryTotals'] as $category)<div class="col-6 col-md-3 col-xl"><div class="small text-muted">{{ $category['label'] }}</div><strong>Q {{ number_format((float) $category['total_amount'], 2) }}</strong><div class="small text-muted">{{ $category['count'] }} documentos</div></div>@endforeach</div></div></div>
@endif

<x-table-container><table class="table table-hover align-middle mb-0 small"><thead><tr><th>Fecha</th><th>Tipo</th><th>Serie / Número</th><th>UUID</th><th>NIT</th><th>{{ $thirdPartyLabel }}</th><th>Categoría</th><th>Base</th><th>Exento</th><th>IVA</th><th>Otros</th><th>Total</th>@if($direction->value === 'PURCHASE')<th>Crédito</th>@endif<th>Estado</th><th></th></tr></thead><tbody>
@forelse($report['documents'] as $document)<tr @class(['table-secondary'=>$document->status->value==='VOIDED'])>
    <td>{{ $document->document_date->format('d/m/Y') }}</td><td><span @class(['badge','text-bg-info'=>$document->document_type->value==='CREDIT_NOTE','text-bg-warning'=>$document->document_type->value==='DEBIT_NOTE','text-bg-light'=>!in_array($document->document_type->value,['CREDIT_NOTE','DEBIT_NOTE'])])>{{ $document->document_type->label() }}</span></td>
    <td>{{ $document->series ?: '—' }} / {{ $document->document_number ?: '—' }}</td><td class="font-monospace">{{ Str::limit($document->authorization_uuid, 18) ?: '—' }}</td><td>{{ $document->third_party_tax_id ?: '—' }}</td><td>{{ $document->third_party_name }}</td><td>{{ $document->tax_category->label() }}</td>
    @foreach(['taxable_amount','exempt_amount','vat_amount','other_taxes_amount','total_amount'] as $field)<td @class(['text-danger'=>$document->document_type->value==='CREDIT_NOTE'])>{{ $document->document_type->value==='CREDIT_NOTE' ? '− ' : '' }}Q {{ number_format((float) $document->{$field},2) }}</td>@endforeach
    @if($direction->value === 'PURCHASE')<td>{{ $document->grants_tax_credit ? 'Sí' : 'No' }}</td>@endif<td><span class="badge text-bg-{{ $document->status->value==='ACTIVE'?'success':'secondary' }}">{{ $document->status->label() }}</span></td><td><a href="{{ route($routePrefix.'.show',$document->id) }}" class="btn btn-sm btn-outline-primary">Ver</a></td>
</tr>@empty<tr><td colspan="16"><x-empty-state title="Sin documentos" message="No hay documentos fiscales para los filtros seleccionados." /></td></tr>@endforelse
</tbody></table></x-table-container><div class="mt-3">{{ $report['documents']->links() }}</div>
@endsection
