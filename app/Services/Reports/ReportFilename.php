<?php

namespace App\Services\Reports;

use App\Models\Company;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class ReportFilename
{
    public static function make(string $reportName, Company $company, CarbonInterface $generatedAt, string $extension): string
    {
        $companySlug = Str::slug($company->name) ?: 'empresa';

        return sprintf(
            '%s-%s-%s.%s',
            Str::slug($reportName),
            $companySlug,
            $generatedAt->format('Y-m-d'),
            ltrim($extension, '.')
        );
    }
}
