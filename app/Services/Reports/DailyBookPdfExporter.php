<?php

namespace App\Services\Reports;

use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;

class DailyBookPdfExporter
{
    public function download(Company $company, array $report, CarbonInterface $generatedAt): Response
    {
        $filename = ReportFilename::make('libro-diario', $company, $generatedAt, 'pdf');

        return Pdf::loadView('accounting.daily_book.exports.pdf', [
            'company' => $company,
            'journalEntries' => $report['entries'],
            'totalDebit' => $report['totalDebit'],
            'totalCredit' => $report['totalCredit'],
            'periodDescription' => $report['periodDescription'],
            'generatedAt' => $generatedAt,
        ])
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->download($filename);
    }
}
