<?php

namespace App\Services\Reports;

use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;

class AccountingPdfExporter
{
    public function download(
        string $view,
        string $slug,
        Company $company,
        array $report,
        CarbonInterface $generatedAt,
        string $orientation = 'portrait',
    ): Response {
        return Pdf::loadView($view, array_merge($report, [
            'company' => $company,
            'generatedAt' => $generatedAt,
        ]))
            ->setPaper('a4', $orientation)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->download(ReportFilename::make($slug, $company, $generatedAt, 'pdf'));
    }
}
