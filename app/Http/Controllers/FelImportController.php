<?php

namespace App\Http\Controllers;

use App\Enums\FelDocumentSourceType;
use App\Enums\FelImportBatchStatus;
use App\Exceptions\FelImportException;
use App\Http\Requests\StoreFelImportRequest;
use App\Models\FelImportBatch;
use App\Services\Fel\FelDocumentImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class FelImportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'source_type' => ['nullable', Rule::enum(FelDocumentSourceType::class)],
            'status' => ['nullable', Rule::enum(FelImportBatchStatus::class)],
            'filename' => ['nullable', 'string', 'max:255'],
        ]);

        $query = FelImportBatch::where('company_id', session('company_id'));
        $query->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['source_type'] ?? null, fn ($q, $value) => $q->where('source_type', $value))
            ->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['filename'] ?? null, fn ($q, $value) => $q->where('original_filename', 'like', '%'.$value.'%'));

        return view('fel.imports.index', ['batches' => $query->latest()->paginate(20)->withQueryString(), 'sourceTypes' => FelDocumentSourceType::cases(), 'statuses' => FelImportBatchStatus::cases()]);
    }

    public function create(): View
    {
        return view('fel.imports.create');
    }

    public function store(StoreFelImportRequest $request, FelDocumentImportService $importer): RedirectResponse
    {
        try {
            $company = $request->user()->companies()->active()->wherePivot('is_active', true)->whereKey(session('company_id'))->firstOrFail();
            $batches = collect($request->file('files'))->map(fn ($file) => $importer->import($file, $company, $request->user()));
        } catch (Throwable $exception) {
            $technical = $exception instanceof FelImportException && $exception->getPrevious() ? $exception->getPrevious() : $exception;
            Log::error('FEL import request failed', [
                'company_id' => session('company_id'),
                'tenant_id' => $request->user()->tenant_id,
                'user_id' => $request->user()->id,
                'error_code' => $exception instanceof FelImportException ? $exception->errorCode : 'FEL-UNKNOWN',
                'processing_stage' => $exception instanceof FelImportException ? $exception->stage : 'request',
                'exception_class' => $technical::class,
                'technical_message' => $technical->getMessage(),
                'exception_file' => $technical->getFile(),
                'exception_line' => $technical->getLine(),
                'stack_trace' => $technical->getTraceAsString(),
            ]);

            $message = $exception instanceof FelImportException
                ? '['.$exception->errorCode.'] '.$exception->getMessage()
                : '[FEL-UNKNOWN] No se pudo iniciar la importación FEL. Intente nuevamente o consulte al administrador.';

            return back()->withInput()->with('error', $message);
        }

        if ($batches->count() === 1) {
            $batch = $batches->first();
            $flash = match ($batch->status) {
                FelImportBatchStatus::FAILED => ['error', 'La importación FEL no pudo completarse. Revise el detalle del lote.'],
                FelImportBatchStatus::COMPLETED_WITH_ERRORS => ['warning', 'La importación FEL terminó con observaciones o archivos no procesados.'],
                default => ['success', 'La importación FEL finalizó correctamente.'],
            };

            return redirect()->route('fel-imports.show', $batch)->with($flash[0], $flash[1]);
        }

        $flash = $batches->contains(fn (FelImportBatch $batch) => in_array($batch->status, [FelImportBatchStatus::FAILED, FelImportBatchStatus::COMPLETED_WITH_ERRORS], true))
            ? 'warning'
            : 'success';

        return redirect()->route('fel-imports.index')->with($flash, $batches->count().' archivos fueron procesados en lotes independientes.');
    }

    public function show(FelImportBatch $felImportBatch): View
    {
        $this->authorizeBatch($felImportBatch);

        return view('fel.imports.show', ['batch' => $felImportBatch->load(['rows' => fn ($q) => $q->latest('id'), 'rows.document:id,authorization_uuid', 'importedBy:id,name'])]);
    }

    private function authorizeBatch(FelImportBatch $batch): void
    {
        abort_unless((int) $batch->company_id === (int) session('company_id'), 403);
    }
}
