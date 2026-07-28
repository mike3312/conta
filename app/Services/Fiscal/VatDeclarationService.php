<?php

namespace App\Services\Fiscal;

use App\Enums\VatDeclarationStatus;
use App\Models\AccountingPeriod;
use App\Models\User;
use App\Models\VatDeclaration;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VatDeclarationService
{
    private const ADJUSTMENT_FIELDS = [
        'previous_credit_balance', 'vat_withholdings', 'vat_perceptions',
        'manual_debit_adjustments', 'manual_credit_adjustments',
    ];

    public function __construct(private readonly VatDeclarationCalculationService $calculator) {}

    public function saveDraft(int $companyId, AccountingPeriod $period, array $data, User $user): array
    {
        $existing = VatDeclaration::forCompany($companyId)->where('accounting_period_id', $period->id)->first();
        if ($existing && $existing->status !== VatDeclarationStatus::DRAFT) {
            throw ValidationException::withMessages(['status' => 'Solo una declaración en borrador puede recalcularse.']);
        }

        $adjustments = collect(self::ADJUSTMENT_FIELDS)->mapWithKeys(fn (string $field) => [
            $field => $data[$field] ?? $existing?->{$field} ?? '0.00',
        ])->all();
        $calculation = $this->calculator->calculate($companyId, $period, $adjustments);

        return DB::transaction(function () use ($existing, $companyId, $period, $data, $user, $calculation) {
            $previous = $existing?->documents()->get()->keyBy('fiscal_document_id');
            $declaration = $existing ?: new VatDeclaration;
            $declaration->fill([
                'company_id' => $companyId,
                'accounting_period_id' => $period->id,
                'tax_regime' => 'GENERAL',
                'status' => VatDeclarationStatus::DRAFT,
                ...$calculation['totals'],
                'notes' => $data['notes'] ?? $declaration->notes,
                'calculated_at' => now(),
                'prepared_by' => $user->id,
            ])->save();

            $declaration->documents()->delete();
            foreach ($calculation['documents'] as $row) {
                $declaration->documents()->create($this->snapshotAttributes($row));
            }

            $current = $declaration->documents()->get()->keyBy('fiscal_document_id');
            $changeSummary = [
                'added' => $previous ? $current->keys()->diff($previous->keys())->count() : $current->count(),
                'removed' => $previous ? $previous->keys()->diff($current->keys())->count() : 0,
            ];

            return ['declaration' => $declaration->fresh(), 'calculation' => $calculation, 'changes' => $changeSummary];
        });
    }

    public function markReviewed(VatDeclaration $declaration, User $user): VatDeclaration
    {
        $this->expectStatus($declaration, VatDeclarationStatus::DRAFT);

        return DB::transaction(function () use ($declaration, $user) {
            $declaration->update([
                'status' => VatDeclarationStatus::REVIEWED,
                'reviewed_at' => now(),
                'reviewed_by' => $user->id,
            ]);

            return $declaration->refresh();
        });
    }

    public function markReady(VatDeclaration $declaration, User $user): VatDeclaration
    {
        $this->expectStatus($declaration, VatDeclarationStatus::REVIEWED);
        $comparison = $this->compareWithBooks($declaration);
        if ($comparison['blockingWarnings'] !== []) {
            throw ValidationException::withMessages([
                'declaration' => collect($comparison['blockingWarnings'])->pluck('message')->all(),
            ]);
        }
        if ($comparison['changed']) {
            throw ValidationException::withMessages(['declaration' => 'Los libros fiscales cambiaron después del último cálculo. Devuelva la declaración a borrador y recalcule.']);
        }

        return DB::transaction(function () use ($declaration, $user) {
            $declaration->update([
                'status' => VatDeclarationStatus::READY_TO_FILE,
                'ready_at' => now(),
                'ready_by' => $user->id,
            ]);

            return $declaration->refresh();
        });
    }

    public function returnToDraft(VatDeclaration $declaration): VatDeclaration
    {
        if (! in_array($declaration->status, [VatDeclarationStatus::REVIEWED, VatDeclarationStatus::READY_TO_FILE], true)) {
            throw ValidationException::withMessages(['status' => 'Esta declaración no puede volver a borrador.']);
        }

        $declaration->update([
            'status' => VatDeclarationStatus::DRAFT,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'ready_at' => null,
            'ready_by' => null,
        ]);

        return $declaration->refresh();
    }

    public function file(VatDeclaration $declaration, array $data, User $user): VatDeclaration
    {
        $this->expectStatus($declaration, VatDeclarationStatus::READY_TO_FILE);

        return DB::transaction(function () use ($declaration, $data, $user) {
            $declaration->update([
                'status' => VatDeclarationStatus::FILED,
                'filed_at' => $data['filed_at'],
                'filed_by' => $user->id,
                'sat_form_number' => $data['sat_form_number'],
                'sat_access_number' => $data['sat_access_number'],
                'sat_payment_slip_number' => $data['sat_payment_slip_number'] ?? null,
                'amount_paid' => $data['amount_paid'],
                'notes' => $data['notes'] ?? $declaration->notes,
            ]);

            return $declaration->refresh();
        });
    }

    public function compareWithBooks(VatDeclaration $declaration): array
    {
        $calculation = $this->calculator->calculate(
            $declaration->company_id,
            $declaration->accountingPeriod,
            $declaration->only(self::ADJUSTMENT_FIELDS),
        );
        $snapshot = $declaration->documents()->get()->keyBy('fiscal_document_id');
        $current = $calculation['documents']->keyBy('fiscal_document_id');
        $added = $current->keys()->diff($snapshot->keys());
        $removed = $snapshot->keys()->diff($current->keys());
        $changed = $current->filter(function (array $row, int $id) use ($snapshot) {
            $old = $snapshot->get($id);

            return ! $old
                || $old->included !== $row['included']
                || $old->fiscal_status->value !== $row['fiscal_status']
                || $old->review_status !== $row['review_status']
                || $old->vat_amount !== $row['vat_amount'];
        })->keys();
        $snapshotTotalsMatch = $this->snapshotTotalsMatch($declaration);
        $blockingWarnings = $calculation['blockingWarnings'];
        if (! $snapshotTotalsMatch) {
            $blockingWarnings[] = [
                'severity' => 'BLOCKING',
                'code' => 'SNAPSHOT_TOTAL_MISMATCH',
                'message' => 'Los totales guardados no coinciden con el snapshot documental.',
            ];
        }
        $vatDifference = $this->toCents($calculation['totals']['vat_payable']) - $this->toCents($declaration->vat_payable);

        return [
            'changed' => $added->isNotEmpty() || $removed->isNotEmpty() || $changed->isNotEmpty() || ! $snapshotTotalsMatch,
            'added' => $added->count(),
            'removed' => $removed->count(),
            'modified' => $changed->count(),
            'currentVat' => $calculation['totals']['net_vat_debit'],
            'snapshotVat' => $declaration->net_vat_debit,
            'vatDifference' => $this->fromCents($vatDifference),
            'blockingWarnings' => $blockingWarnings,
        ];
    }

    private function snapshotTotalsMatch(VatDeclaration $declaration): bool
    {
        $fields = [
            'sales_taxable_amount', 'sales_exempt_amount', 'sales_non_taxable_amount',
            'sales_vat_amount', 'sales_other_taxes_amount', 'purchase_taxable_amount',
            'purchase_exempt_amount', 'purchase_non_taxable_amount', 'purchase_vat_amount',
            'purchase_creditable_vat_amount', 'purchase_non_creditable_vat_amount',
            'purchase_other_taxes_amount',
        ];
        $totals = array_fill_keys($fields, 0);
        foreach ($declaration->documents()->where('included', true)->get() as $snapshot) {
            $prefix = $snapshot->direction->value === 'SALE' ? 'sales' : 'purchase';
            foreach (['taxable_amount', 'exempt_amount', 'non_taxable_amount', 'vat_amount', 'other_taxes_amount'] as $field) {
                $totals[$prefix.'_'.$field] += $snapshot->effect * $this->toCents($snapshot->{$field});
            }
            if ($prefix === 'purchase') {
                $totals['purchase_creditable_vat_amount'] += $snapshot->effect * $this->toCents($snapshot->creditable_vat_amount);
                $totals['purchase_non_creditable_vat_amount'] += $snapshot->effect * $this->toCents($snapshot->non_creditable_vat_amount);
            }
        }

        return collect($fields)->every(fn (string $field) => $this->toCents($declaration->{$field}) === $totals[$field]);
    }

    private function snapshotAttributes(array $row): array
    {
        return collect($row)->only([
            'fiscal_document_id', 'direction', 'document_type', 'review_status', 'fiscal_status',
            'taxable_amount', 'exempt_amount', 'non_taxable_amount', 'vat_amount',
            'creditable_vat_amount', 'non_creditable_vat_amount', 'other_taxes_amount',
            'total_amount', 'effect', 'included', 'exclusion_reason', 'source_snapshot',
        ])->all();
    }

    private function expectStatus(VatDeclaration $declaration, VatDeclarationStatus $status): void
    {
        if ($declaration->status !== $status) {
            throw ValidationException::withMessages(['status' => 'La declaración no se encuentra en el estado requerido para esta acción.']);
        }
    }

    private function toCents(string|int $amount): int
    {
        $amount = (string) $amount;
        $negative = str_starts_with($amount, '-');
        [$whole, $decimal] = array_pad(explode('.', ltrim($amount, '-'), 2), 2, '0');
        $cents = ((int) $whole * 100) + (int) str_pad(substr($decimal, 0, 2), 2, '0');

        return $negative ? -$cents : $cents;
    }

    private function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return $sign.intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
