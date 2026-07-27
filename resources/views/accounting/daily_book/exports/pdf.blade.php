<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Libro Diario</title>
    <style>
        @page { margin: 22mm 10mm 16mm; }
        * { box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; color: #172033; font-size: 9px; margin: 0; }
        h1 { color: #12315b; font-size: 20px; margin: 0 0 5px; }
        .report-header { border-bottom: 2px solid #1f6feb; margin-bottom: 12px; padding-bottom: 9px; }
        .company-name { font-size: 13px; font-weight: bold; }
        .meta { color: #516176; line-height: 1.55; }
        table { border-collapse: collapse; width: 100%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #12315b; color: #fff; font-size: 8px; padding: 6px 5px; text-align: left; }
        td { border-bottom: 1px solid #dce3ec; padding: 5px; vertical-align: top; }
        .entry-start td { border-top: 1.5px solid #7f91aa; }
        .number, .date { white-space: nowrap; }
        .amount { text-align: right; white-space: nowrap; }
        .totals td { background: #eaf2fb; border-top: 2px solid #12315b; font-weight: bold; padding: 7px 5px; }
        .empty { border: 1px solid #dce3ec; color: #66758a; padding: 22px; text-align: center; }
        .footer { bottom: -10mm; color: #66758a; font-size: 8px; left: 0; position: fixed; right: 0; text-align: right; }
        .page-number::after { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">ERP Conta · Página <span class="page-number"></span></div>

    <div class="report-header">
        <h1>Libro Diario</h1>
        <div class="company-name">{{ $company->name }}</div>
        <div class="meta">
            @if($company->tax_id) NIT: {{ $company->tax_id }} · @endif
            Moneda: {{ $company->currency ?: 'GTQ' }}<br>
            {{ $periodDescription }}<br>
            Generado: {{ $generatedAt->format('d/m/Y H:i:s') }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%">Póliza</th>
                <th style="width: 9%">Fecha</th>
                <th style="width: 25%">Concepto</th>
                <th style="width: 12%">Código</th>
                <th>Cuenta</th>
                <th style="width: 12%; text-align: right">Debe</th>
                <th style="width: 12%; text-align: right">Haber</th>
            </tr>
        </thead>
        <tbody>
            @if($journalEntries->isEmpty())
                <tr>
                    <td colspan="7" class="empty">No existen movimientos contabilizados para los filtros seleccionados.</td>
                </tr>
            @else
                @foreach($journalEntries as $journalEntry)
                    @foreach($journalEntry->lines as $line)
                        <tr @class(['entry-start' => $loop->first])>
                            <td class="number">{{ $loop->first ? $journalEntry->number : '' }}</td>
                            <td class="date">{{ $loop->first ? $journalEntry->entry_date->format('d/m/Y') : '' }}</td>
                            <td>{{ $loop->first ? $journalEntry->description : '' }}</td>
                            <td>{{ $line->account->code }}</td>
                            <td>{{ $line->account->name }}</td>
                            <td class="amount">{{ number_format((float) $line->debit, 2) }}</td>
                            <td class="amount">{{ number_format((float) $line->credit, 2) }}</td>
                        </tr>
                    @endforeach
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr class="totals">
                <td colspan="5" style="text-align: right">Totales generales (GTQ)</td>
                <td class="amount">{{ number_format((float) $totalDebit, 2) }}</td>
                <td class="amount">{{ number_format((float) $totalCredit, 2) }}</td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
