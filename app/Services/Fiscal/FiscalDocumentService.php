<?php

namespace App\Services\Fiscal;

use App\Enums\AccountingPeriodStatus;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use App\Models\AccountingPeriod;
use App\Models\FiscalDocument;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FiscalDocumentService
{
    public function create(int $companyId, FiscalDocumentDirection $direction, array $data, User $user): FiscalDocument
    {
        $this->assertReferencesBelongToCompany($companyId, $data);
        $this->assertPeriodIsOpen($companyId, $data['document_date'], $data['accounting_period_id'] ?? null);
        $attributes = $this->normalize($data, $direction);

        return DB::transaction(fn () => FiscalDocument::create([
            ...$attributes,
            'company_id' => $companyId,
            'direction' => $direction,
            'status' => FiscalDocumentStatus::ACTIVE,
            'created_by' => $user->id,
        ]));
    }

    public function update(FiscalDocument $document, array $data, User $user): FiscalDocument
    {
        $this->ensureModifiable($document);
        $this->assertReferencesBelongToCompany($document->company_id, $data);
        $this->assertPeriodIsOpen($document->company_id, $data['document_date'], $data['accounting_period_id'] ?? null);

        DB::transaction(fn () => $document->update([
            ...$this->normalize($data, $document->direction),
            'updated_by' => $user->id,
        ]));

        return $document->refresh();
    }

    public function void(FiscalDocument $document, User $user, ?string $reason = null): FiscalDocument
    {
        $this->ensureModifiable($document);

        DB::transaction(fn () => $document->update([
            'status' => FiscalDocumentStatus::VOIDED,
            'voided_by' => $user->id,
            'voided_at' => now(),
            'void_reason' => $reason,
            'updated_by' => $user->id,
        ]));

        return $document->refresh();
    }

    public function effectiveSign(FiscalDocumentType $type): int
    {
        return $type === FiscalDocumentType::CREDIT_NOTE ? -1 : 1;
    }

    public function ensureModifiable(FiscalDocument $document): void
    {
        $this->assertActive($document);
        $this->assertPeriodIsOpen($document->company_id, $document->document_date->toDateString(), $document->accounting_period_id);
    }

    private function normalize(array $data, FiscalDocumentDirection $direction): array
    {
        $smallTaxpayer = ($data['tax_category'] ?? null) === FiscalTaxCategory::SMALL_TAXPAYER->value
            || (bool) ($data['is_small_taxpayer'] ?? false);
        if ($smallTaxpayer) {
            $data['tax_category'] = FiscalTaxCategory::SMALL_TAXPAYER->value;
            $data['is_small_taxpayer'] = true;
            $data['vat_amount'] = '0.00';
            $data['grants_tax_credit'] = false;
        }
        if (($data['tax_category'] ?? null) === FiscalTaxCategory::EXEMPT->value) {
            $data['vat_amount'] = '0.00';
        }
        if ($direction === FiscalDocumentDirection::SALE) {
            $data['grants_tax_credit'] = false;
        }

        return $data;
    }

    private function assertReferencesBelongToCompany(int $companyId, array $data): void
    {
        if (! empty($data['accounting_period_id'])
            && ! AccountingPeriod::where('company_id', $companyId)->whereKey($data['accounting_period_id'])->exists()) {
            throw ValidationException::withMessages(['accounting_period_id' => 'El período contable no pertenece a la empresa activa.']);
        }
        if (! empty($data['journal_entry_id'])
            && ! JournalEntry::where('company_id', $companyId)->whereKey($data['journal_entry_id'])->exists()) {
            throw ValidationException::withMessages(['journal_entry_id' => 'La póliza contable no pertenece a la empresa activa.']);
        }
    }

    private function assertPeriodIsOpen(int $companyId, string $documentDate, ?int $periodId): void
    {
        $closed = AccountingPeriod::where('company_id', $companyId)
            ->where('status', AccountingPeriodStatus::CLOSED->value)
            ->where(function ($query) use ($documentDate, $periodId) {
                $query->where(fn ($query) => $query->whereDate('start_date', '<=', $documentDate)->whereDate('end_date', '>=', $documentDate));
                if ($periodId) {
                    $query->orWhere('id', $periodId);
                }
            })
            ->exists();

        if ($closed) {
            throw ValidationException::withMessages(['document_date' => 'No se puede modificar un documento fiscal dentro de un período contable cerrado.']);
        }
    }

    private function assertActive(FiscalDocument $document): void
    {
        if ($document->status !== FiscalDocumentStatus::ACTIVE) {
            throw ValidationException::withMessages(['status' => 'El documento fiscal ya está anulado y no puede modificarse.']);
        }
    }
}
