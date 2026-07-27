<?php

namespace App\Http\Controllers;

use App\Enums\FelOperationType;
use App\Models\Company;
use App\Models\FelDocument;
use App\Services\Fel\FelReclassificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class FelReclassificationController extends Controller
{
    public function index(Request $request): View
    {
        $company = $this->activeCompany($request);
        $documents = FelDocument::query()->where('company_id', $company->id);

        return view('fel.reclassification.index', [
            'company' => $company,
            'maskedTaxId' => $this->maskedTaxId($company->tax_id),
            'unknownCount' => (clone $documents)->where('operation_type', FelOperationType::UNKNOWN->value)->count(),
            'documentCount' => $documents->count(),
            'reviewedCount' => (clone $documents)->whereNotNull('reviewed_at')->count(),
        ]);
    }

    public function preview(Request $request, FelReclassificationService $service): RedirectResponse
    {
        $company = $this->activeCompany($request);
        if (! $this->hasTaxId($company)) {
            return $this->missingTaxIdResponse();
        }

        $scope = $this->validatedScope($request);

        try {
            $summary = $service->preview($company->id, $scope === 'unknown');
        } catch (Throwable $exception) {
            $this->logRequestFailure('preview', $request, $company, $scope, $exception);

            return redirect()->route('fel.reclassification.index')
                ->with('error', 'No fue posible preparar la vista previa. Intente nuevamente.');
        }

        session()->put('fel_reclassification_preview', [
            'company_id' => $company->id,
            'scope' => $scope,
            'created_at' => now()->timestamp,
        ]);

        return redirect()->route('fel.reclassification.preview.show')
            ->with('fel_reclassification_preview_result', [
                'company_id' => $company->id,
                'scope' => $scope,
                'summary' => Arr::except($summary, ['company_tax_id']),
            ]);
    }

    public function showPreview(Request $request): View|RedirectResponse
    {
        $company = $this->activeCompany($request);
        $previewResult = session('fel_reclassification_preview_result');
        if ((int) data_get($previewResult, 'company_id') !== (int) $company->id) {
            return redirect()->route('fel.reclassification.index');
        }

        return view('fel.reclassification.preview', [
            'company' => $company,
            'scope' => data_get($previewResult, 'scope'),
            'summary' => data_get($previewResult, 'summary'),
        ]);
    }

    public function execute(Request $request, FelReclassificationService $service): RedirectResponse
    {
        $company = $this->activeCompany($request);
        if (! $this->hasTaxId($company)) {
            return $this->missingTaxIdResponse();
        }

        $scope = $this->validatedScope($request);
        $preview = session()->pull('fel_reclassification_preview');
        abort_unless(
            (int) data_get($preview, 'company_id') === (int) $company->id
            && data_get($preview, 'scope') === $scope
            && (int) data_get($preview, 'created_at') >= now()->subMinutes(30)->timestamp,
            403,
        );

        Log::info('FEL reclassification started', $this->auditContext($request, $company, $scope));

        try {
            $summary = $service->execute($company->id, $scope === 'unknown');
        } catch (Throwable $exception) {
            $this->logRequestFailure('execute', $request, $company, $scope, $exception);

            return redirect()->route('fel.reclassification.index')
                ->with('error', 'No fue posible completar la reclasificación. Intente nuevamente.');
        }

        $context = [
            ...$this->auditContext($request, $company, $scope),
            'processed' => $summary['processed'],
            'changed' => $summary['changed'],
            'failed' => $summary['failed'],
            'result' => $summary['failed'] > 0 ? 'partial' : 'success',
        ];
        Log::log($summary['failed'] > 0 ? 'warning' : 'info', 'FEL reclassification finished', $context);

        return redirect()->route('fel.reclassification.result')
            ->with('fel_reclassification_result', [
                'company_id' => $company->id,
                'scope' => $scope,
                'summary' => Arr::except($summary, ['company_tax_id']),
            ]);
    }

    public function result(Request $request): View|RedirectResponse
    {
        $company = $this->activeCompany($request);
        $result = session('fel_reclassification_result');
        if ((int) data_get($result, 'company_id') !== (int) $company->id) {
            return redirect()->route('fel-documents.index');
        }

        return view('fel.reclassification.result', [
            'company' => $company,
            'scope' => data_get($result, 'scope'),
            'summary' => data_get($result, 'summary'),
        ]);
    }

    private function validatedScope(Request $request): string
    {
        return $request->validate([
            'scope' => ['required', Rule::in(['unknown', 'all'])],
        ])['scope'];
    }

    private function activeCompany(Request $request): Company
    {
        $user = $request->user();
        $company = $user->companies()
            ->whereKey(session('company_id'))
            ->where('companies.tenant_id', $user->tenant_id)
            ->wherePivot('is_active', true)
            ->first();

        abort_unless($company, 403);

        return $company;
    }

    private function hasTaxId(Company $company): bool
    {
        return trim((string) $company->tax_id) !== '';
    }

    private function missingTaxIdResponse(): RedirectResponse
    {
        return redirect()->route('fel.reclassification.index')
            ->with('error', 'No es posible reclasificar los documentos porque la empresa activa no tiene un NIT configurado.');
    }

    private function maskedTaxId(?string $taxId): string
    {
        $taxId = trim((string) $taxId);
        if ($taxId === '') {
            return 'No configurado';
        }

        return mb_strlen($taxId) <= 4
            ? str_repeat('•', mb_strlen($taxId))
            : str_repeat('•', mb_strlen($taxId) - 4).mb_substr($taxId, -4);
    }

    private function auditContext(Request $request, Company $company, string $scope): array
    {
        return [
            'user_id' => $request->user()->id,
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'scope' => $scope === 'unknown' ? 'unknown_only' : 'all',
            'recorded_at' => now()->toIso8601String(),
        ];
    }

    private function logRequestFailure(string $stage, Request $request, Company $company, string $scope, Throwable $exception): void
    {
        Log::error('FEL reclassification request failed', [
            ...$this->auditContext($request, $company, $scope),
            'stage' => $stage,
            'exception' => $exception::class,
        ]);
    }
}
