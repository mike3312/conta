<?php

namespace App\Services\Fel;

use App\Enums\AccountingPeriodStatus;
use App\Enums\FelDocumentStatus;
use App\Enums\FelFiscalStatus;
use App\Models\Company;
use App\Models\FelDocument;
use App\Models\FelDocumentReviewLog;
use App\Models\User;
use App\Services\Fiscal\FelFiscalDocumentSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FelBulkReviewService
{
    public function __construct(private readonly FelFiscalDocumentSyncService $fiscalSync) {}

    public function process(
        Company $company,
        User $user,
        array $documentIds,
        FelDocumentStatus $target,
        ?string $reason = null,
        string $actionType = 'BULK',
    ): array {
        $ids = collect($documentIds)->map(fn ($id) => (int) $id)->unique()->values();
        $batchUuid = (string) Str::uuid();
        $summary = ['selected' => $ids->count(), 'processed' => 0, 'skipped' => 0, 'errors' => 0, 'details' => [], 'batch_uuid' => $batchUuid];

        foreach ($ids->chunk(100) as $chunk) {
            $documents = FelDocument::query()
                ->where('company_id', $company->id)
                ->where('tenant_id', $company->tenant_id)
                ->whereIn('id', $chunk)
                ->with('fiscalDocument.accountingPeriod')
                ->get()
                ->keyBy('id');

            foreach ($chunk as $id) {
                $document = $documents->get($id);
                if (! $document) {
                    $summary['errors']++;
                    $summary['details'][] = ['id' => $id, 'result' => 'ERROR', 'message' => 'No pertenece a la empresa activa o no está disponible.'];

                    continue;
                }

                try {
                    $result = DB::transaction(fn () => $this->processOne($document, $user, $target, $reason, $actionType, $batchUuid));
                    $summary[strtolower($result['result']) === 'processed' ? 'processed' : 'skipped']++;
                    $summary['details'][] = ['id' => $id, ...$result];
                } catch (Throwable $exception) {
                    report($exception);
                    $summary['errors']++;
                    $summary['details'][] = ['id' => $id, 'result' => 'ERROR', 'message' => 'No fue posible procesar el documento.'];
                }
            }
        }

        return $summary;
    }

    private function processOne(FelDocument $document, User $user, FelDocumentStatus $target, ?string $reason, string $actionType, string $batchUuid): array
    {
        $previous = $document->status;
        $skipReason = $this->ineligibilityReason($document, $target);
        if ($skipReason) {
            $this->log($document, $user, $previous, $previous, $reason, $actionType, $batchUuid, 'SKIPPED', $skipReason);

            return ['result' => 'SKIPPED', 'message' => $skipReason];
        }

        $syncMetadata = null;
        if ($target === FelDocumentStatus::APPROVED) {
            $sync = $this->fiscalSync->syncFromFel($document, $user);
            $syncMetadata = $sync->toArray();
            if ($sync->skipped() || $sync->errors !== []) {
                $message = $sync->errors[0] ?? $sync->warnings[0] ?? 'El documento no pudo sincronizarse con los libros fiscales.';
                $this->log($document, $user, $previous, $previous, $reason, $actionType, $batchUuid, 'SKIPPED', $message, $syncMetadata);

                return ['result' => 'SKIPPED', 'message' => $message];
            }
        }

        $document->update([
            'status' => $target,
            'observation' => $target === FelDocumentStatus::OBSERVED ? $reason : null,
            'rejection_reason' => $target === FelDocumentStatus::REJECTED ? $reason : null,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);
        $this->log($document, $user, $previous, $target, $reason, $actionType, $batchUuid, 'PROCESSED', null, $syncMetadata);

        return ['result' => 'PROCESSED', 'message' => 'Estado actualizado a '.$target->value.'.'];
    }

    private function ineligibilityReason(FelDocument $document, FelDocumentStatus $target): ?string
    {
        if ($document->fiscal_status === FelFiscalStatus::VOIDED) {
            return 'El documento está anulado fiscalmente.';
        }
        if ($document->status === $target) {
            return 'El documento ya tiene el estado solicitado.';
        }
        if ($document->fiscalDocument?->accountingPeriod?->status === AccountingPeriodStatus::CLOSED) {
            return 'El documento pertenece a un período contable cerrado.';
        }

        return null;
    }

    private function log(
        FelDocument $document,
        User $user,
        FelDocumentStatus $previous,
        FelDocumentStatus $new,
        ?string $reason,
        string $actionType,
        string $batchUuid,
        string $result,
        ?string $message = null,
        ?array $sync = null,
    ): void {
        FelDocumentReviewLog::create([
            'company_id' => $document->company_id,
            'fel_document_id' => $document->id,
            'batch_action_uuid' => $batchUuid,
            'previous_status' => $previous,
            'new_status' => $new,
            'reason' => $reason,
            'action_type' => $actionType,
            'performed_by' => $user->id,
            'performed_at' => now(),
            'metadata' => array_filter(['result' => $result, 'message' => $message, 'fiscal_sync' => $sync]),
        ]);
    }
}
