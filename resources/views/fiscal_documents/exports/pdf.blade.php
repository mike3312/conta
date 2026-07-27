<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><style>
@page{margin:18px}body{font-family:DejaVu Sans,sans-serif;font-size:8px;color:#172033}.header{border-bottom:2px solid #0b1739;margin-bottom:10px;padding-bottom:8px}.company{font-size:15px;font-weight:bold;text-transform:uppercase}.title{font-size:18px;font-weight:bold;color:#0b1739;margin:5px 0}.meta-table{border-collapse:collapse;width:100%}.meta-table td{border:0;padding:2px 10px 2px 0}.summary{margin:10px 0}.summary td{border:1px solid #ccd3dd;background:#f4f7fb;padding:6px;width:25%}.summary strong{display:block;font-size:10px;margin-top:2px}table.data{border-collapse:collapse;width:100%}.data th,.data td{border:1px solid #ccd3dd;padding:4px}.data th{background:#0b1739;color:#fff;text-align:left}.money{text-align:right;white-space:nowrap}.voided{color:#777;background:#eee}.totals{font-weight:bold;background:#eaf1ff}.categories{margin-top:14px;border-collapse:collapse;width:100%}.categories td{border:1px solid #ccd3dd;padding:5px;width:25%}
</style></head><body>
<div class="header">
    <div class="company">{{ $company->legal_name ?: $company->name }}</div>
    <div><strong>NIT:</strong> {{ $company->tax_id }}</div>
    <div class="title">{{ $direction->value === 'PURCHASE' ? 'LIBRO DE COMPRAS' : 'LIBRO DE VENTAS' }}</div>
    <table class="meta-table"><tr>
        <td><strong>Período:</strong> {{ $report['periodName'] }}<br><strong>Rango:</strong> {{ $report['periodRange'] }}</td>
        <td><strong>Estado:</strong> {{ $report['reviewStatusLabel'] }}<br><strong>Generado:</strong> {{ $generatedAt->format('d/m/Y H:i:s') }} por {{ $user->name }}</td>
    </tr></table>
</div>
<table class="summary"><tr>
    <td>Cantidad de documentos<strong>{{ $report['shownCount'] }}</strong></td>
    <td>Subtotal / base imponible<strong>Q {{ number_format((float)$report['totals']['taxable_amount'],2) }}</strong></td>
    <td>IVA<strong>Q {{ number_format((float)$report['totals']['vat_amount'],2) }}</strong></td>
    <td>Total<strong>Q {{ number_format((float)$report['totals']['total_amount'],2) }}</strong></td>
</tr></table>
<table class="data"><thead><tr><th>Fecha</th><th>Tipo</th><th>Serie / Número</th><th>NIT</th><th>Tercero</th><th>Categoría</th><th>Base</th><th>Exento</th><th>IVA</th><th>Otros</th><th>Total</th><th>Estado</th></tr></thead><tbody>
@foreach($report['documents'] as $document)
    @php
        $reviewStatus = $document->status->value === 'VOIDED' ? 'VOIDED' : ($document->felDocument?->status?->value ?? 'APPROVED');
        $reviewLabel = ['APPROVED' => 'Aprobado', 'OBSERVED' => 'Observado', 'REJECTED' => 'Rechazado', 'VOIDED' => 'Anulado'][$reviewStatus] ?? $reviewStatus;
    @endphp
    <tr class="{{ $reviewStatus === 'VOIDED' ? 'voided' : '' }}"><td>{{ $document->document_date->format('d/m/Y') }}</td><td>{{ $document->document_type->label() }}</td><td>{{ $document->series }} / {{ $document->document_number }}</td><td>{{ $document->third_party_tax_id }}</td><td>{{ $document->third_party_name }}</td><td>{{ $document->tax_category->label() }}</td>@foreach(['taxable_amount','exempt_amount','vat_amount','other_taxes_amount','total_amount'] as $field)<td class="money">{{ $document->document_type->value==='CREDIT_NOTE'?'- ':'' }}Q {{ number_format((float)$document->{$field},2) }}</td>@endforeach<td>{{ $reviewLabel }}</td></tr>
@endforeach
<tr class="totals"><td colspan="6">Totales fiscales del filtro</td><td class="money">Q {{ number_format((float)$report['totals']['taxable_amount'],2) }}</td><td class="money">Q {{ number_format((float)$report['totals']['exempt_amount'],2) }}</td><td class="money">Q {{ number_format((float)$report['totals']['vat_amount'],2) }}</td><td class="money">Q {{ number_format((float)$report['totals']['other_taxes_amount'],2) }}</td><td class="money">Q {{ number_format((float)$report['totals']['total_amount'],2) }}</td><td></td></tr>
</tbody></table>
@if($direction->value === 'PURCHASE')<table class="categories"><tr>@foreach($report['categoryTotals'] as $category)<td><strong>{{ $category['label'] }}</strong><br>Q {{ number_format((float)$category['total_amount'],2) }} · {{ $category['count'] }} docs.</td>@endforeach</tr></table>@endif
</body></html>
