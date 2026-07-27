<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use App\Models\AccountingPeriod;
use App\Models\FiscalDocument;
use Illuminate\Database\Eloquent\Builder;

class FiscalBooksService
{
    public function __construct(private readonly FiscalDocumentService $documentService) {}

    private const AMOUNT_FIELDS = [
        'taxable_amount', 'exempt_amount', 'non_taxable_amount', 'vat_amount',
        'other_taxes_amount', 'total_amount',
    ];

    public function build(
        int $companyId,
        FiscalDocumentDirection $direction,
        array $filters,
        ?int $perPage = null,
    ): array {
        $query = $this->query($companyId, $direction, $filters);
        $totals = array_fill_keys(self::AMOUNT_FIELDS, 0);
        $count = 0;
        $categories = collect(FiscalTaxCategory::cases())->mapWithKeys(fn ($category) => [$category->value => [
            'label' => $category->label(), 'count' => 0, 'total_amount' => 0,
        ]])->all();

        (clone $query)->active()->orderBy('id')->eachById(function (FiscalDocument $document) use (&$totals, &$count, &$categories) {
            $sign = $this->documentService->effectiveSign($document->document_type);
            $count++;
            foreach (self::AMOUNT_FIELDS as $field) {
                $totals[$field] += $sign * $this->toCents($document->{$field});
            }
            $categories[$document->tax_category->value]['count']++;
            $categories[$document->tax_category->value]['total_amount'] += $sign * $this->toCents($document->total_amount);
        });

        $documentsQuery = (clone $query)->with(['accountingPeriod', 'journalEntry'])->orderBy('document_date')->orderBy('id');
        $documents = $perPage
            ? $documentsQuery->paginate($perPage)->withQueryString()
            : $documentsQuery->get();

        return [
            'documents' => $documents,
            'count' => $count,
            'totals' => collect($totals)->map(fn (int $cents) => $this->fromCents($cents))->all(),
            'categoryTotals' => collect($categories)->map(fn (array $category) => [
                ...$category,
                'total_amount' => $this->fromCents($category['total_amount']),
            ])->all(),
            'filters' => $filters,
            'periodDescription' => $this->periodDescription($companyId, $filters),
        ];
    }

    public function options(int $companyId): array
    {
        return [
            'periods' => AccountingPeriod::where('company_id', $companyId)->orderByDesc('start_date')->get(),
            'types' => FiscalDocumentType::cases(),
            'categories' => FiscalTaxCategory::cases(),
            'statuses' => FiscalDocumentStatus::cases(),
        ];
    }

    private function query(int $companyId, FiscalDocumentDirection $direction, array $filters): Builder
    {
        return FiscalDocument::query()
            ->forCompany($companyId)
            ->where('direction', $direction->value)
            ->betweenDates($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->forPeriod(isset($filters['accounting_period_id']) ? (int) $filters['accounting_period_id'] : null)
            ->when($filters['document_type'] ?? null, fn ($query, $value) => $query->where('document_type', $value))
            ->when($filters['tax_category'] ?? null, fn ($query, $value) => $query->where('tax_category', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['third_party_tax_id'] ?? null, fn ($query, $value) => $query->where('third_party_tax_id', 'like', "%{$value}%"))
            ->when($filters['third_party_name'] ?? null, fn ($query, $value) => $query->where('third_party_name', 'like', "%{$value}%"))
            ->when($filters['series'] ?? null, fn ($query, $value) => $query->where('series', 'like', "%{$value}%"))
            ->when($filters['document_number'] ?? null, fn ($query, $value) => $query->where('document_number', 'like', "%{$value}%"))
            ->when($filters['authorization_uuid'] ?? null, fn ($query, $value) => $query->where('authorization_uuid', 'like', "%{$value}%"))
            ->when(isset($filters['grants_tax_credit']) && $filters['grants_tax_credit'] !== '', fn ($query) => $query->where('grants_tax_credit', (bool) $filters['grants_tax_credit']))
            ->when(isset($filters['is_small_taxpayer']) && $filters['is_small_taxpayer'] !== '', fn ($query) => $query->where('is_small_taxpayer', (bool) $filters['is_small_taxpayer']));
    }

    private function periodDescription(int $companyId, array $filters): string
    {
        if (! empty($filters['accounting_period_id'])) {
            $period = AccountingPeriod::where('company_id', $companyId)->find($filters['accounting_period_id']);
            if ($period) {
                return 'Período: '.$period->name;
            }
        }
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            return $filters['date_from'].' al '.$filters['date_to'];
        }

        return ! empty($filters['date_from']) ? 'Desde '.$filters['date_from'] : (! empty($filters['date_to']) ? 'Hasta '.$filters['date_to'] : 'Todos los períodos');
    }

    private function toCents(string $amount): int
    {
        $negative = str_starts_with($amount, '-');
        $amount = ltrim($amount, '-');
        [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '0');
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
