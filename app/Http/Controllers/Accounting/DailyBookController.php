<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\DailyBookReportRequest;
use App\Models\Company;
use App\Services\Reports\DailyBookExcelExporter;
use App\Services\Reports\DailyBookPdfExporter;
use App\Services\Reports\DailyBookReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DailyBookController extends Controller
{
    public function __construct(
        private readonly DailyBookReportService $reportService,
        private readonly DailyBookPdfExporter $pdfExporter,
        private readonly DailyBookExcelExporter $excelExporter,
    ) {}

    public function index(DailyBookReportRequest $request): View|RedirectResponse
    {
        $company = $this->activeCompany($request);

        if (! $company) {
            return redirect()
                ->route('companies.index')
                ->with('error', 'Primero debes seleccionar una empresa activa.');
        }

        $filters = $this->reportService->normalizeFilters($company->id, $request->validated());
        $options = $this->reportService->filterOptions($company->id);
        $report = $this->reportService->build($company->id, $filters, 15);

        return view('accounting.daily_book.index', [
            'journalEntries' => $report['entries'],
            'periods' => $options['periods'],
            'accounts' => $options['accounts'],
            'filters' => $filters,
            'totalDebit' => $report['totalDebit'],
            'totalCredit' => $report['totalCredit'],
            'isBalanced' => $report['isBalanced'],
        ]);
    }

    public function exportPdf(DailyBookReportRequest $request): Response
    {
        $company = $this->activeCompany($request);
        abort_unless($company, 403);

        $filters = $this->reportService->normalizeFilters($company->id, $request->validated());
        $report = $this->reportService->build($company->id, $filters);

        return $this->pdfExporter->download($company, $report, now($company->timezone));
    }

    public function exportExcel(DailyBookReportRequest $request): BinaryFileResponse
    {
        $company = $this->activeCompany($request);
        abort_unless($company, 403);

        $filters = $this->reportService->normalizeFilters($company->id, $request->validated());
        $report = $this->reportService->build($company->id, $filters);

        return $this->excelExporter->download($company, $report, now($company->timezone));
    }

    private function activeCompany(DailyBookReportRequest $request): ?Company
    {
        $companyId = (int) session('company_id');

        if (! $companyId) {
            return null;
        }

        return $request->user()
            ->companies()
            ->active()
            ->wherePivot('is_active', true)
            ->whereKey($companyId)
            ->first();
    }
}
