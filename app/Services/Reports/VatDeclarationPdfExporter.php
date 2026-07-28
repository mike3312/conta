<?php

namespace App\Services\Reports;

use App\Models\Company;
use App\Models\User;
use App\Models\VatDeclaration;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;

class VatDeclarationPdfExporter
{
    public function download(VatDeclaration $declaration, Company $company, User $user, CarbonInterface $generatedAt): Response
    {
        return Pdf::loadView('vat_declarations.exports.pdf', compact('declaration', 'company', 'user', 'generatedAt'))
            ->setPaper('a4', 'landscape')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', false)
            ->download(ReportFilename::make('declaracion-iva', $company, $generatedAt, 'pdf'));
    }
}
