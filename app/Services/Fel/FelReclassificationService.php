<?php

namespace App\Services\Fel;

use App\Enums\FelDocumentClassification;
use App\Enums\FelOperationType;
use App\Models\Company;
use App\Models\FelDocument;
use App\Models\User;
use App\Services\Fiscal\FelFiscalDocumentSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FelReclassificationService
{
    public function __construct(
        private readonly FelClassificationService $classifier,
        private readonly ?FelFiscalDocumentSyncService $fiscalSync = null,
    ) {}

    public function preview(int $companyId, bool $onlyUnknown = true, ?User $user = null): array
    {
        return $this->run(
            companyId: $companyId,
            onlyUnknown: $onlyUnknown,
            dryRun: true,
            includeReviewed: true,
            collectChanges: true,
            user: $user,
        );
    }

    public function execute(int $companyId, bool $onlyUnknown = true, ?User $user = null): array
    {
        return $this->run(
            companyId: $companyId,
            onlyUnknown: $onlyUnknown,
            includeReviewed: true,
            user: $user,
        );
    }

    public function run(
        int $companyId,
        bool $onlyUnknown = false,
        bool $dryRun = false,
        bool $includeReviewed = false,
        ?callable $onChange = null,
        bool $collectChanges = false,
        int $changeLimit = 100,
        ?User $user = null,
    ): array {
        // Always reload the current company and its current NIT from the database.
        $company = Company::query()->findOrFail($companyId);
        $query = FelDocument::query()
            ->where('company_id', $company->getKey())
            ->with(['items:id,fel_document_id,description', 'taxes:id,fel_document_id,tax_name']);

        if ($onlyUnknown) {
            $query->where('operation_type', FelOperationType::UNKNOWN->value);
        }

        $reviewedSkipped = (clone $query)->whereNotNull('reviewed_at')->count();
        if (! $includeReviewed) {
            $query->whereNull('reviewed_at');
        } else {
            $reviewedSkipped = 0;
        }

        $summary = [
            'company_id' => $company->getKey(),
            'company_name' => $company->name,
            'company_tax_id' => $company->tax_id,
            'candidates' => $query->count(),
            'processed' => 0,
            'changed' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'reviewed_skipped' => $reviewedSkipped,
            'reviewed_included' => (clone $query)->whereNotNull('reviewed_at')->count(),
            'purchases' => 0,
            'sales' => 0,
            'unknown' => 0,
            'general_purchases' => 0,
            'general_sales' => 0,
            'fuel' => 0,
            'lodging' => 0,
            'tax_review_required' => 0,
            'fiscal_created' => 0,
            'fiscal_updated' => 0,
            'fiscal_unchanged' => 0,
            'fiscal_observed' => 0,
            'fiscal_conflicts' => 0,
            'changes' => [],
            'changes_truncated' => false,
            'dry_run' => $dryRun,
        ];

        $query->orderBy('id')->chunkById(100, function ($documents) use ($company, $dryRun, $onChange, $collectChanges, $changeLimit, $user, &$summary) {
            foreach ($documents as $document) {
                try {
                    $classification = $this->classifier->classify($this->classificationData($document), $company->tax_id);
                    $changes = [
                        'operation_type' => $classification['operation_type'],
                        'classification' => $classification['classification'],
                        'requires_tax_review' => $classification['requires_tax_review'],
                    ];
                    $isChanged = $document->operation_type !== $changes['operation_type']
                        || $document->classification !== $changes['classification']
                        || $document->requires_tax_review !== $changes['requires_tax_review'];

                    if ($isChanged) {
                        if ($onChange) {
                            $onChange($document, $changes, $dryRun);
                        }
                        if (! $dryRun) {
                            DB::transaction(fn () => $document->update($changes));
                        }
                        $summary['changed']++;
                        if ($collectChanges && count($summary['changes']) < $changeLimit) {
                            $summary['changes'][] = $this->changeSummary($document, $changes);
                        } elseif ($collectChanges) {
                            $summary['changes_truncated'] = true;
                        }
                    } else {
                        $summary['unchanged']++;
                    }

                    $syncDocument = $dryRun ? clone $document : $document->refresh();
                    if ($dryRun) {
                        $syncDocument->setRawAttributes([...$document->getAttributes(),
                            'operation_type' => $changes['operation_type']->value,
                            'classification' => $changes['classification']->value,
                            'requires_tax_review' => $changes['requires_tax_review'],
                        ], true);
                    }
                    $sync = ($this->fiscalSync ?? app(FelFiscalDocumentSyncService::class))
                        ->syncFromFel($syncDocument, $user, $dryRun);
                    match ($sync->action) {
                        'CREATED' => $summary['fiscal_created']++,
                        'UPDATED' => $summary['fiscal_updated']++,
                        'UNCHANGED' => $summary['fiscal_unchanged']++,
                        'CONFLICT' => $summary['fiscal_conflicts']++,
                        default => $summary['fiscal_observed']++,
                    };

                    $summary['processed']++;
                    $this->addClassificationTotals($summary, $changes);
                } catch (Throwable $exception) {
                    $summary['failed']++;
                    Log::error('FEL reclassification document failed', [
                        'tenant_id' => $company->tenant_id,
                        'company_id' => $company->getKey(),
                        'document_id' => $document->getKey(),
                        'exception' => $exception::class,
                    ]);
                }
            }
        });

        return $summary;
    }

    private function addClassificationTotals(array &$summary, array $changes): void
    {
        match ($changes['operation_type']) {
            FelOperationType::PURCHASE => $summary['purchases']++,
            FelOperationType::SALE => $summary['sales']++,
            FelOperationType::UNKNOWN => $summary['unknown']++,
        };
        match ($changes['classification']) {
            FelDocumentClassification::GENERAL_PURCHASE => $summary['general_purchases']++,
            FelDocumentClassification::GENERAL_SALE => $summary['general_sales']++,
            FelDocumentClassification::FUEL => $summary['fuel']++,
            FelDocumentClassification::LODGING => $summary['lodging']++,
            FelDocumentClassification::UNCLASSIFIED => null,
        };
        if ($changes['requires_tax_review']) {
            $summary['tax_review_required']++;
        }
    }

    private function changeSummary(FelDocument $document, array $changes): array
    {
        return [
            'uuid' => mb_strlen($document->authorization_uuid) > 18
                ? mb_substr($document->authorization_uuid, 0, 8).'…'.mb_substr($document->authorization_uuid, -6)
                : $document->authorization_uuid,
            'issuer' => $document->issuer_name ?: 'Sin nombre',
            'receiver' => $document->receiver_name ?: 'Sin nombre',
            'current_operation' => $document->operation_type->value,
            'proposed_operation' => $changes['operation_type']->value,
            'current_classification' => $document->classification->value,
            'proposed_classification' => $changes['classification']->value,
        ];
    }

    private function classificationData(FelDocument $document): array
    {
        return [
            'issuer' => ['tax_id' => $document->issuer_tax_id],
            'receiver' => ['tax_id' => $document->receiver_tax_id],
            'taxes' => $document->taxes->map(fn ($tax) => ['tax_name' => $tax->tax_name])->all(),
            'items' => $document->items->map(fn ($item) => ['description' => $item->description])->all(),
            'complements' => $document->complements ?? [],
        ];
    }
}
