<?php

namespace App\Services\Reports;

use App\Enums\FiscalDocumentDirection;
use App\Models\Company;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;

class FiscalBookPdfExporter
{
    public function download(Company $company, User $user, FiscalDocumentDirection $direction, array $report, CarbonInterface $generatedAt): Response
    {
        $slug = $direction === FiscalDocumentDirection::PURCHASE ? 'libro-compras' : 'libro-ventas';

        return Pdf::loadView('fiscal_documents.exports.pdf', [
            'company' => $company,
            'user' => $user,
            'direction' => $direction,
            'report' => $report,
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->download(ReportFilename::make($slug, $company, $generatedAt, 'pdf'));
    }
}
