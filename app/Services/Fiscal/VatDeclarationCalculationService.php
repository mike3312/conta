<?php

namespace App\Services\Fiscal;

use App\Enums\FelDocumentStatus;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Models\AccountingPeriod;
use App\Models\FiscalDocument;

class VatDeclarationCalculationService
{
    private const AMOUNT_FIELDS = [
        'taxable_amount', 'exempt_amount', 'non_taxable_amount', 'vat_amount',
        'other_taxes_amount', 'total_amount',
    ];

    public function __construct(private readonly FiscalDocumentService $documents) {}

    public function calculate(int $companyId, AccountingPeriod $period, array $adjustments = []): array
    {
        abort_unless((int) $period->company_id === $companyId, 403);

        $candidates = FiscalDocument::query()
            ->forCompany($companyId)
            ->with('felDocument:id,status')
            ->where(function ($query) use ($period) {
                $query->where('accounting_period_id', $period->id)
                    ->orWhere(function ($query) use ($period) {
                        $query->whereNull('accounting_period_id')
                            ->whereDate('document_date', '>=', $period->start_date)
                            ->whereDate('document_date', '<=', $period->end_date);
                    });
            })
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        $totals = array_fill_keys([
            'sales_taxable_amount', 'sales_exempt_amount', 'sales_non_taxable_amount',
            'sales_vat_amount', 'sales_other_taxes_amount', 'purchase_taxable_amount',
            'purchase_exempt_amount', 'purchase_non_taxable_amount', 'purchase_vat_amount',
            'purchase_creditable_vat_amount', 'purchase_non_creditable_vat_amount',
            'purchase_other_taxes_amount',
        ], 0);
        $counters = [
            'included' => 0, 'observed' => 0, 'rejected' => 0, 'voided' => 0,
            'incomplete' => 0, 'credit_undefined' => 0, 'without_period' => 0,
        ];
        $warnings = [];
        $rows = [];

        foreach ($candidates as $document) {
            $row = $this->classify($document);
            $rows[] = $row;
            if (! $row['included']) {
                $counter = $row['counter'] ?? null;
                if ($counter) {
                    $counters[$counter]++;
                }
                $warnings[] = [
                    'severity' => $row['blocking'] ? 'BLOCKING' : 'WARNING',
                    'code' => $row['exclusion_code'],
                    'message' => $row['exclusion_reason'],
                    'fiscal_document_id' => $document->id,
                    'direction' => $document->direction->value,
                ];

                continue;
            }

            $counters['included']++;
            $prefix = $document->direction === FiscalDocumentDirection::SALE ? 'sales' : 'purchase';
            $effect = $row['effect'];
            foreach (['taxable_amount', 'exempt_amount', 'non_taxable_amount', 'vat_amount', 'other_taxes_amount'] as $field) {
                $totals[$prefix.'_'.$field] += $effect * $this->toCents($row[$field]);
            }
            if ($document->direction === FiscalDocumentDirection::PURCHASE) {
                $creditField = $document->grants_tax_credit
                    ? 'purchase_creditable_vat_amount'
                    : 'purchase_non_creditable_vat_amount';
                $totals[$creditField] += $effect * $this->toCents($row['vat_amount']);
            }
        }

        $money = collect([
            'previous_credit_balance', 'vat_withholdings', 'vat_perceptions',
            'manual_debit_adjustments', 'manual_credit_adjustments',
        ])->mapWithKeys(fn (string $field) => [$field => max(0, $this->toCents($adjustments[$field] ?? '0'))])->all();
        $netDebit = max(0, $totals['sales_vat_amount'] + $money['manual_debit_adjustments'] + $money['vat_perceptions']);
        $netCredit = max(0, $totals['purchase_creditable_vat_amount'] + $money['previous_credit_balance'] + $money['manual_credit_adjustments'] + $money['vat_withholdings']);
        $payable = max(0, $netDebit - $netCredit);
        $creditBalance = max(0, $netCredit - $netDebit);

        $totals = [
            ...$totals,
            ...$money,
            'net_vat_debit' => $netDebit,
            'net_vat_credit' => $netCredit,
            'vat_payable' => $payable,
            'credit_balance' => $creditBalance,
        ];

        return [
            'period' => $period,
            'documents' => collect($rows),
            'includedDocuments' => collect($rows)->where('included', true)->values(),
            'excludedDocuments' => collect($rows)->where('included', false)->values(),
            'totals' => collect($totals)->map(fn (int $cents) => $this->fromCents($cents))->all(),
            'counters' => $counters,
            'warnings' => $warnings,
            'blockingWarnings' => array_values(array_filter($warnings, fn (array $warning) => $warning['severity'] === 'BLOCKING')),
        ];
    }

    private function classify(FiscalDocument $document): array
    {
        $reviewStatus = $document->felDocument?->status?->value ?? FelDocumentStatus::APPROVED->value;
        $reason = null;
        $code = null;
        $counter = null;
        $blocking = false;

        if ($document->status === FiscalDocumentStatus::VOIDED) {
            [$code, $reason, $counter] = ['VOIDED', 'El documento está anulado fiscalmente.', 'voided'];
        } elseif ($reviewStatus !== FelDocumentStatus::APPROVED->value) {
            $counter = $reviewStatus === FelDocumentStatus::REJECTED->value ? 'rejected' : 'observed';
            [$code, $reason] = [$reviewStatus, 'El documento no tiene revisión aprobada.'];
        } elseif ($document->accounting_period_id === null) {
            [$code, $reason, $counter, $blocking] = ['WITHOUT_PERIOD', 'El documento aprobado no tiene período contable asignado.', 'without_period', true];
        } elseif (strtoupper($document->currency) !== 'GTQ' && $this->rateMicros($document->exchange_rate) <= 0) {
            [$code, $reason, $counter, $blocking] = ['INVALID_EXCHANGE_RATE', 'La moneda extranjera no tiene un tipo de cambio válido.', 'incomplete', true];
        } elseif (trim((string) $document->third_party_name) === '' || $this->hasNegativeAmounts($document)) {
            [$code, $reason, $counter, $blocking] = ['INCOMPLETE_DATA', 'El documento contiene información fiscal incompleta o importes negativos inconsistentes.', 'incomplete', true];
        } elseif ($document->direction === FiscalDocumentDirection::PURCHASE && $document->grants_tax_credit === null) {
            [$code, $reason, $counter, $blocking] = ['CREDIT_UNDEFINED', 'La compra requiere definir si otorga derecho a crédito fiscal.', 'credit_undefined', true];
        }

        $amounts = collect(self::AMOUNT_FIELDS)->mapWithKeys(fn (string $field) => [
            $field => $this->convertedAmount($document->{$field}, $document->currency, $document->exchange_rate),
        ])->all();

        return [
            'fiscal_document_id' => $document->id,
            'direction' => $document->direction->value,
            'document_type' => $document->document_type->value,
            'document_type_label' => $document->document_type->label(),
            'review_status' => $reviewStatus,
            'fiscal_status' => $document->status->value,
            ...$amounts,
            'creditable_vat_amount' => $document->direction === FiscalDocumentDirection::PURCHASE && $document->grants_tax_credit ? $amounts['vat_amount'] : '0.00',
            'non_creditable_vat_amount' => $document->direction === FiscalDocumentDirection::PURCHASE && ! $document->grants_tax_credit ? $amounts['vat_amount'] : '0.00',
            'effect' => $this->documents->effectiveSign($document->document_type),
            'included' => $reason === null,
            'exclusion_code' => $code,
            'exclusion_reason' => $reason,
            'counter' => $counter,
            'blocking' => $blocking,
            'source_snapshot' => [
                'document_date' => $document->document_date->toDateString(),
                'series' => $document->series,
                'document_number' => $document->document_number,
                'authorization_uuid' => $document->authorization_uuid,
                'third_party_tax_id' => $document->third_party_tax_id,
                'third_party_name' => $document->third_party_name,
                'currency' => $document->currency,
                'exchange_rate' => $document->exchange_rate,
                'tax_category' => $document->tax_category->value,
                'grants_tax_credit' => $document->grants_tax_credit,
            ],
        ];
    }

    private function convertedAmount(string $amount, string $currency, string $exchangeRate): string
    {
        $cents = $this->toCents($amount);
        if (strtoupper($currency) !== 'GTQ') {
            $cents = intdiv(($cents * $this->rateMicros($exchangeRate)) + 500000, 1000000);
        }

        return $this->fromCents($cents);
    }

    private function hasNegativeAmounts(FiscalDocument $document): bool
    {
        return collect(self::AMOUNT_FIELDS)->contains(fn (string $field) => $this->toCents($document->{$field}) < 0);
    }

    private function rateMicros(string $rate): int
    {
        [$whole, $decimal] = array_pad(explode('.', ltrim($rate, '-'), 2), 2, '0');
        $micros = ((int) $whole * 1000000) + (int) str_pad(substr($decimal, 0, 6), 6, '0');

        return str_starts_with($rate, '-') ? -$micros : $micros;
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
