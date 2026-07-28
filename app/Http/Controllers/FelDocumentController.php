<?php

namespace App\Http\Controllers;

use App\Enums\FelDocumentClassification;
use App\Enums\FelDocumentDataLevel;
use App\Enums\FelDocumentSourceType;
use App\Enums\FelDocumentStatus;
use App\Enums\FelFiscalStatus;
use App\Enums\FelOperationType;
use App\Http\Requests\ObserveFelDocumentRequest;
use App\Http\Requests\RejectFelDocumentRequest;
use App\Models\AccountingPeriod;
use App\Models\FelDocument;
use App\Services\Fel\FelBulkReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FelDocumentController extends Controller
{
    public function __construct(private readonly FelBulkReviewService $reviews) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'dte_type' => ['nullable', 'string', 'max:30'],
            'operation_type' => ['nullable', Rule::enum(FelOperationType::class)],
            'classification' => ['nullable', Rule::enum(FelDocumentClassification::class)],
            'status' => ['nullable', Rule::enum(FelDocumentStatus::class)],
            'fiscal_status' => ['nullable', Rule::enum(FelFiscalStatus::class)],
            'source_type' => ['nullable', Rule::enum(FelDocumentSourceType::class)],
            'data_level' => ['nullable', Rule::enum(FelDocumentDataLevel::class)],
            'requires_tax_review' => ['nullable', 'boolean'],
            'uuid' => ['nullable', 'string', 'max:100'],
            'nit' => ['nullable', 'string', 'max:50'],
            'name' => ['nullable', 'string', 'max:255'],
            'issuer_tax_regime' => ['nullable', 'string', 'max:100'],
            'fel_import_batch_id' => ['nullable', 'integer'],
            'accounting_period_id' => [
                'nullable', 'integer',
                Rule::exists('accounting_periods', 'id')->where(fn ($query) => $query->where('company_id', session('company_id'))),
            ],
        ]);

        $query = FelDocument::forActiveCompany()->where('tenant_id', $request->user()->tenant_id);
        foreach (['dte_type', 'operation_type', 'classification', 'status', 'fiscal_status', 'source_type', 'data_level', 'issuer_tax_regime', 'fel_import_batch_id'] as $field) {
            $query->when($filters[$field] ?? null, fn ($query, $value) => $query->where($field, $value));
        }
        $query->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('issued_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('issued_at', '<=', $date))
            ->when(array_key_exists('requires_tax_review', $filters), fn ($query) => $query->where('requires_tax_review', $request->boolean('requires_tax_review')))
            ->when($filters['uuid'] ?? null, fn ($query, $value) => $query->where('authorization_uuid', 'like', '%'.strtoupper($value).'%'))
            ->when($filters['nit'] ?? null, fn ($query, $value) => $query->where(fn ($sub) => $sub->where('issuer_tax_id', 'like', '%'.$value.'%')->orWhere('receiver_tax_id', 'like', '%'.$value.'%')))
            ->when($filters['name'] ?? null, fn ($query, $value) => $query->where(fn ($sub) => $sub->where('issuer_name', 'like', '%'.$value.'%')->orWhere('receiver_name', 'like', '%'.$value.'%')));
        $query->when($filters['accounting_period_id'] ?? null, fn ($query, $periodId) => $query->whereHas(
            'fiscalDocument',
            fn ($query) => $query->where('accounting_period_id', $periodId),
        ));

        $activeCompany = $request->user()->companies()
            ->whereKey(session('company_id'))
            ->where('companies.tenant_id', $request->user()->tenant_id)
            ->wherePivot('is_active', true)
            ->firstOrFail();

        return view('fel.documents.index', [
            'documents' => $query->with('fiscalDocument.accountingPeriod')->latest('issued_at')->paginate(25)->withQueryString(),
            'filters' => $filters,
            'activeCompany' => $activeCompany,
            'activeCompanyName' => $activeCompany->name,
            'operationTypes' => FelOperationType::cases(),
            'classifications' => FelDocumentClassification::cases(),
            'statuses' => FelDocumentStatus::cases(),
            'fiscalStatuses' => FelFiscalStatus::cases(),
            'sourceTypes' => FelDocumentSourceType::cases(),
            'dataLevels' => FelDocumentDataLevel::cases(),
            'periods' => AccountingPeriod::where('company_id', $activeCompany->id)->latest('start_date')->get(),
        ]);
    }

    public function show(FelDocument $felDocument): View
    {
        $this->authorizeDocument($felDocument);

        return view('fel.documents.show', ['document' => $felDocument->load(['items', 'taxes', 'importedBy:id,name', 'reviewedBy:id,name', 'fiscalDocument'])]);
    }

    public function approve(Request $request, FelDocument $felDocument): RedirectResponse
    {
        return $this->review($request, $felDocument, FelDocumentStatus::APPROVED, null, 'Documento aprobado. No se creó ninguna póliza contable.');
    }

    public function observe(ObserveFelDocumentRequest $request, FelDocument $felDocument): RedirectResponse
    {
        return $this->review($request, $felDocument, FelDocumentStatus::OBSERVED, $request->validated('observation'), 'Documento marcado como observado.');
    }

    public function reject(RejectFelDocumentRequest $request, FelDocument $felDocument): RedirectResponse
    {
        return $this->review($request, $felDocument, FelDocumentStatus::REJECTED, $request->validated('rejection_reason'), 'Documento rechazado y conservado para auditoría.');
    }

    public function downloadXml(FelDocument $felDocument): StreamedResponse|RedirectResponse
    {
        $this->authorizeDocument($felDocument);

        try {
            $disk = Storage::disk(config('fel.disk'));
            if (! $felDocument->xml_path || ! $disk->exists($felDocument->xml_path)) {
                Log::warning('FEL XML download unavailable', ['company_id' => $felDocument->company_id, 'document_id' => $felDocument->id]);

                return back()->with('error', 'El XML original no está disponible.');
            }

            return $disk->download($felDocument->xml_path, $felDocument->authorization_uuid.'.xml', ['Content-Type' => 'application/xml']);
        } catch (Throwable $exception) {
            Log::error('FEL XML download failed', ['company_id' => $felDocument->company_id, 'document_id' => $felDocument->id, 'exception' => $exception::class]);

            return back()->with('error', 'No se pudo descargar el XML original. Intente nuevamente.');
        }
    }

    private function review(Request $request, FelDocument $document, FelDocumentStatus $status, ?string $reason, string $successMessage): RedirectResponse
    {
        $this->authorizeDocument($document);

        try {
            $summary = $this->reviews->process($document->company, $request->user(), [$document->id], $status, $reason, 'INDIVIDUAL');
            if ($summary['processed'] === 0) {
                return back()->with('warning', $summary['details'][0]['message'] ?? 'El documento no pudo procesarse.');
            }

            return back()->with('success', $successMessage);
        } catch (Throwable $exception) {
            Log::error('FEL document review update failed', [
                'action' => $status->value,
                'company_id' => $document->company_id,
                'document_id' => $document->id,
                'user_id' => $request->user()->id,
                'exception' => $exception::class,
            ]);

            return back()->with('error', 'No se pudo guardar la revisión del documento. Intente nuevamente.');
        }
    }

    private function authorizeDocument(FelDocument $document): void
    {
        abort_unless(
            (int) $document->company_id === (int) session('company_id')
            && (int) $document->tenant_id === (int) request()->user()->tenant_id,
            403,
        );
        Gate::authorize('review', $document);
    }
}
