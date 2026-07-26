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
use App\Models\FelDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FelDocumentController extends Controller
{
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
        ]);

        $query = FelDocument::forActiveCompany();
        foreach (['dte_type', 'operation_type', 'classification', 'status', 'fiscal_status', 'source_type', 'data_level'] as $field) {
            $query->when($filters[$field] ?? null, fn ($q, $value) => $q->where($field, $value));
        }
        $query->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('issued_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('issued_at', '<=', $date))
            ->when(array_key_exists('requires_tax_review', $filters), fn ($q) => $q->where('requires_tax_review', $request->boolean('requires_tax_review')))
            ->when($filters['uuid'] ?? null, fn ($q, $value) => $q->where('authorization_uuid', 'like', '%'.strtoupper($value).'%'))
            ->when($filters['nit'] ?? null, fn ($q, $value) => $q->where(fn ($sub) => $sub->where('issuer_tax_id', 'like', '%'.$value.'%')->orWhere('receiver_tax_id', 'like', '%'.$value.'%')))
            ->when($filters['name'] ?? null, fn ($q, $value) => $q->where(fn ($sub) => $sub->where('issuer_name', 'like', '%'.$value.'%')->orWhere('receiver_name', 'like', '%'.$value.'%')));

        return view('fel.documents.index', ['documents' => $query->latest('issued_at')->paginate(25)->withQueryString(), 'operationTypes' => FelOperationType::cases(), 'classifications' => FelDocumentClassification::cases(), 'statuses' => FelDocumentStatus::cases(), 'fiscalStatuses' => FelFiscalStatus::cases(), 'sourceTypes' => FelDocumentSourceType::cases(), 'dataLevels' => FelDocumentDataLevel::cases()]);
    }

    public function show(FelDocument $felDocument): View
    {
        $this->authorizeDocument($felDocument);

        return view('fel.documents.show', ['document' => $felDocument->load(['items', 'taxes', 'importedBy:id,name', 'reviewedBy:id,name'])]);
    }

    public function approve(Request $request, FelDocument $felDocument): RedirectResponse
    {
        return $this->updateReview($request, $felDocument, [
            'status' => FelDocumentStatus::APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ], 'approve', 'Documento aprobado. No se creó ninguna póliza contable.');
    }

    public function observe(ObserveFelDocumentRequest $request, FelDocument $felDocument): RedirectResponse
    {
        return $this->updateReview($request, $felDocument, [
            'status' => FelDocumentStatus::OBSERVED,
            'observation' => $request->validated('observation'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ], 'observe', 'Documento marcado como observado.');
    }

    public function reject(RejectFelDocumentRequest $request, FelDocument $felDocument): RedirectResponse
    {
        return $this->updateReview($request, $felDocument, [
            'status' => FelDocumentStatus::REJECTED,
            'rejection_reason' => $request->validated('rejection_reason'),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ], 'reject', 'Documento rechazado y conservado para auditoría.');
    }

    public function downloadXml(FelDocument $felDocument): StreamedResponse|RedirectResponse
    {
        $this->authorizeDocument($felDocument);

        try {
            $disk = Storage::disk(config('fel.disk'));
            if (! $felDocument->xml_path || ! $disk->exists($felDocument->xml_path)) {
                Log::warning('FEL XML download unavailable', [
                    'company_id' => $felDocument->company_id,
                    'document_id' => $felDocument->id,
                ]);

                return back()->with('error', 'El XML original no está disponible.');
            }

            return $disk->download($felDocument->xml_path, $felDocument->authorization_uuid.'.xml', ['Content-Type' => 'application/xml']);
        } catch (Throwable $exception) {
            Log::error('FEL XML download failed', [
                'company_id' => $felDocument->company_id,
                'document_id' => $felDocument->id,
                'exception' => $exception::class,
            ]);

            return back()->with('error', 'No se pudo descargar el XML original. Intente nuevamente.');
        }
    }

    private function updateReview(Request $request, FelDocument $document, array $attributes, string $action, string $successMessage): RedirectResponse
    {
        $this->authorizeDocument($document);

        try {
            $document->update($attributes);

            return back()->with('success', $successMessage);
        } catch (Throwable $exception) {
            Log::error('FEL document review update failed', [
                'action' => $action,
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
        abort_unless((int) $document->company_id === (int) session('company_id'), 403);
    }
}
