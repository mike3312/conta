<?php

namespace App\Http\Controllers;

use App\Enums\FelDocumentStatus;
use App\Http\Requests\BulkReviewFelDocumentsRequest;
use App\Models\FelDocument;
use App\Services\Fel\FelBulkReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class FelBulkReviewController extends Controller
{
    public function __invoke(BulkReviewFelDocumentsRequest $request, FelBulkReviewService $service): RedirectResponse
    {
        $company = $request->user()->companies()
            ->active()
            ->where('companies.tenant_id', $request->user()->tenant_id)
            ->wherePivot('is_active', true)
            ->whereKey((int) session('company_id'))
            ->first();
        abort_unless($company, 403);
        Gate::authorize('bulkReview', [FelDocument::class, $company]);

        $summary = $service->process(
            $company,
            $request->user(),
            $request->validated('document_ids'),
            FelDocumentStatus::from($request->validated('action')),
            $request->validated('reason'),
        );

        return redirect()->route('fel-documents.index', $request->validated('filters', []))
            ->with('success', 'Procesamiento masivo completado.')
            ->with('fel_bulk_review_result', $summary);
    }
}
