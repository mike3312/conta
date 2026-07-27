<?php

namespace App\Http\Controllers\Fiscal;

use App\Enums\FiscalDocumentDirection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\FiscalBookRequest;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\JournalEntry;
use App\Services\Fiscal\FiscalBooksService;
use App\Services\Fiscal\FiscalDocumentService;
use App\Services\Reports\FiscalBookExcelExporter;
use App\Services\Reports\FiscalBookPdfExporter;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

abstract class FiscalBookController extends Controller
{
    public function __construct(
        protected readonly FiscalDocumentService $documentService,
        protected readonly FiscalBooksService $booksService,
        protected readonly FiscalBookPdfExporter $pdfExporter,
        protected readonly FiscalBookExcelExporter $excelExporter,
    ) {}

    abstract protected function direction(): FiscalDocumentDirection;

    abstract protected function routePrefix(): string;

    public function index(FiscalBookRequest $request): View
    {
        $company = $this->activeCompany($request);

        return view('fiscal_documents.index', [
            ...$this->moduleData(),
            'company' => $company,
            'report' => $this->booksService->build($company->id, $this->direction(), $request->validated(), 25),
            ...$this->booksService->options($company->id),
        ]);
    }

    public function create(Request $request): View
    {
        $company = $this->activeCompany($request);

        return view('fiscal_documents.create', [
            ...$this->moduleData(),
            ...$this->formOptions($company->id),
            'document' => new FiscalDocument,
        ]);
    }

    public function show(Request $request, string|int $document): View
    {
        $company = $this->activeCompany($request);

        return view('fiscal_documents.show', [
            ...$this->moduleData(),
            'document' => $this->findDocument($company->id, (int) $document)->load(['accountingPeriod', 'journalEntry', 'createdBy', 'updatedBy', 'voidedBy']),
        ]);
    }

    public function edit(Request $request, string|int $document): View|RedirectResponse
    {
        $company = $this->activeCompany($request);
        $fiscalDocument = $this->findDocument($company->id, (int) $document);
        try {
            $this->documentService->ensureModifiable($fiscalDocument);
        } catch (ValidationException $exception) {
            return redirect()->route($this->routePrefix().'.show', $fiscalDocument->id)
                ->with('warning', $exception->validator->errors()->first());
        }

        return view('fiscal_documents.edit', [
            ...$this->moduleData(),
            ...$this->formOptions($company->id),
            'document' => $fiscalDocument,
        ]);
    }

    public function void(Request $request, string|int $document): RedirectResponse
    {
        $validated = $request->validate(['void_reason' => ['nullable', 'string', 'max:1000']]);
        $company = $this->activeCompany($request);
        $fiscalDocument = $this->findDocument($company->id, (int) $document);
        $this->documentService->void($fiscalDocument, $request->user(), $validated['void_reason'] ?? null);

        return redirect()->route($this->routePrefix().'.show', $fiscalDocument->id)
            ->with('success', 'Documento fiscal anulado correctamente.');
    }

    public function exportPdf(FiscalBookRequest $request): Response
    {
        $company = $this->activeCompany($request);
        $report = $this->booksService->build($company->id, $this->direction(), $request->validated());

        return $this->pdfExporter->download($company, $request->user(), $this->direction(), $report, now($company->timezone));
    }

    public function exportExcel(FiscalBookRequest $request): BinaryFileResponse
    {
        $company = $this->activeCompany($request);
        $report = $this->booksService->build($company->id, $this->direction(), $request->validated());

        return $this->excelExporter->download($company, $request->user(), $this->direction(), $report, now($company->timezone));
    }

    protected function activeCompany(Request $request): Company
    {
        $company = $request->user()->companies()
            ->active()
            ->where('companies.tenant_id', $request->user()->tenant_id)
            ->wherePivot('is_active', true)
            ->whereKey((int) session('company_id'))
            ->first();

        if (! $company) {
            throw new HttpResponseException(redirect()->route('companies.index')
                ->with('warning', 'Primero debes seleccionar una empresa activa.'));
        }

        return $company;
    }

    protected function findDocument(int $companyId, int $documentId): FiscalDocument
    {
        return FiscalDocument::forCompany($companyId)
            ->where('direction', $this->direction()->value)
            ->findOrFail($documentId);
    }

    protected function moduleData(): array
    {
        $purchase = $this->direction() === FiscalDocumentDirection::PURCHASE;

        return [
            'direction' => $this->direction(),
            'routePrefix' => $this->routePrefix(),
            'bookTitle' => $purchase ? 'Libro de Compras' : 'Libro de Ventas',
            'documentLabel' => $purchase ? 'compra' : 'venta',
            'thirdPartyLabel' => $purchase ? 'Proveedor' : 'Cliente',
        ];
    }

    private function formOptions(int $companyId): array
    {
        return [
            ...$this->booksService->options($companyId),
            'periods' => AccountingPeriod::where('company_id', $companyId)->orderByDesc('start_date')->get(),
            'journalEntries' => JournalEntry::where('company_id', $companyId)->orderByDesc('entry_date')->limit(200)->get(),
        ];
    }
}
