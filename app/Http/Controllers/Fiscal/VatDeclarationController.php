<?php

namespace App\Http\Controllers\Fiscal;

use App\Enums\VatDeclarationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fiscal\FileVatDeclarationRequest;
use App\Http\Requests\Fiscal\SaveVatDeclarationRequest;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\VatDeclaration;
use App\Services\Fiscal\VatDeclarationCalculationService;
use App\Services\Fiscal\VatDeclarationService;
use App\Services\Reports\VatDeclarationExcelExporter;
use App\Services\Reports\VatDeclarationPdfExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VatDeclarationController extends Controller
{
    public function __construct(
        private readonly VatDeclarationCalculationService $calculator,
        private readonly VatDeclarationService $declarations,
        private readonly VatDeclarationPdfExporter $pdf,
        private readonly VatDeclarationExcelExporter $excel,
    ) {}

    public function index(Request $request): View
    {
        $company = $this->activeCompany($request);
        Gate::authorize('viewAny', [VatDeclaration::class, $company]);

        return view('vat_declarations.index', [
            'company' => $company,
            'periods' => AccountingPeriod::where('company_id', $company->id)->latest('start_date')->get(),
            'declarations' => VatDeclaration::forCompany($company->id)->with('accountingPeriod')->latest()->paginate(20),
        ]);
    }

    public function preview(SaveVatDeclarationRequest $request): View|RedirectResponse
    {
        $company = $this->activeCompany($request);
        Gate::authorize('create', [VatDeclaration::class, $company]);
        $period = AccountingPeriod::where('company_id', $company->id)->findOrFail($request->integer('accounting_period_id'));
        $existing = VatDeclaration::forCompany($company->id)->where('accounting_period_id', $period->id)->first();
        if ($existing && $existing->status !== VatDeclarationStatus::DRAFT) {
            return redirect()->route('vat-declarations.show', $existing);
        }

        $values = [...($existing?->toArray() ?? []), ...$request->validated()];

        return view('vat_declarations.preview', [
            'company' => $company,
            'period' => $period,
            'existing' => $existing,
            'values' => $values,
            'calculation' => $this->calculator->calculate($company->id, $period, $values),
        ]);
    }

    public function store(SaveVatDeclarationRequest $request): RedirectResponse
    {
        $company = $this->activeCompany($request);
        Gate::authorize('create', [VatDeclaration::class, $company]);
        $period = AccountingPeriod::where('company_id', $company->id)->findOrFail($request->integer('accounting_period_id'));
        $result = $this->declarations->saveDraft($company->id, $period, $request->validated(), $request->user());

        return redirect()->route('vat-declarations.show', $result['declaration'])
            ->with('success', 'Borrador de IVA guardado con su snapshot documental.');
    }

    public function show(Request $request, VatDeclaration $vatDeclaration): View
    {
        $company = $this->activeCompany($request);
        $this->authorizeDeclaration($vatDeclaration, $company);

        return view('vat_declarations.show', [
            'company' => $company,
            'declaration' => $vatDeclaration->load(['accountingPeriod', 'documents.fiscalDocument', 'preparedBy', 'reviewedBy', 'readyBy', 'filedBy']),
            'comparison' => $this->declarations->compareWithBooks($vatDeclaration),
        ]);
    }

    public function recalculate(SaveVatDeclarationRequest $request, VatDeclaration $vatDeclaration): RedirectResponse
    {
        $company = $this->activeCompany($request);
        $this->authorizeDeclaration($vatDeclaration, $company, 'update');
        $result = $this->declarations->saveDraft($company->id, $vatDeclaration->accountingPeriod, $request->validated(), $request->user());

        return back()->with('success', sprintf(
            'Recalculada: %d documentos agregados y %d retirados.',
            $result['changes']['added'],
            $result['changes']['removed'],
        ));
    }

    public function review(Request $request, VatDeclaration $vatDeclaration): RedirectResponse
    {
        $company = $this->activeCompany($request);
        $this->authorizeDeclaration($vatDeclaration, $company, 'review');
        $this->declarations->markReviewed($vatDeclaration, $request->user());

        return back()->with('success', 'La declaración quedó marcada como revisada.');
    }

    public function ready(Request $request, VatDeclaration $vatDeclaration): RedirectResponse
    {
        $company = $this->activeCompany($request);
        $this->authorizeDeclaration($vatDeclaration, $company, 'review');
        $this->declarations->markReady($vatDeclaration, $request->user());

        return back()->with('success', 'La declaración está lista para su presentación manual ante SAT.');
    }

    public function draft(Request $request, VatDeclaration $vatDeclaration): RedirectResponse
    {
        $company = $this->activeCompany($request);
        $this->authorizeDeclaration($vatDeclaration, $company, 'review');
        $this->declarations->returnToDraft($vatDeclaration);

        return back()->with('success', 'La declaración volvió a borrador y puede recalcularse.');
    }

    public function file(FileVatDeclarationRequest $request, VatDeclaration $vatDeclaration): RedirectResponse
    {
        $company = $this->activeCompany($request);
        $this->authorizeDeclaration($vatDeclaration, $company, 'file');
        $this->declarations->file($vatDeclaration, $request->validated(), $request->user());

        return back()->with('success', 'La presentación manual quedó registrada. No se generaron pólizas ni pagos contables.');
    }

    public function exportPdf(Request $request, VatDeclaration $vatDeclaration): Response
    {
        $company = $this->activeCompany($request);
        $this->authorizeDeclaration($vatDeclaration, $company, 'export');

        return $this->pdf->download($vatDeclaration->load(['accountingPeriod', 'documents.fiscalDocument']), $company, $request->user(), now($company->timezone));
    }

    public function exportExcel(Request $request, VatDeclaration $vatDeclaration): BinaryFileResponse
    {
        $company = $this->activeCompany($request);
        $this->authorizeDeclaration($vatDeclaration, $company, 'export');

        return $this->excel->download($vatDeclaration->load(['accountingPeriod', 'documents.fiscalDocument']), $company, $request->user(), now($company->timezone));
    }

    private function activeCompany(Request $request): Company
    {
        $company = $request->user()->companies()
            ->active()
            ->where('companies.tenant_id', $request->user()->tenant_id)
            ->wherePivot('is_active', true)
            ->whereKey((int) session('company_id'))
            ->first();
        abort_unless($company, 403);

        return $company;
    }

    private function authorizeDeclaration(VatDeclaration $declaration, Company $company, string $ability = 'view'): void
    {
        abort_unless((int) $declaration->company_id === (int) $company->id, 403);
        $declaration->setRelation('company', $company);
        Gate::authorize($ability, $declaration);
    }
}
