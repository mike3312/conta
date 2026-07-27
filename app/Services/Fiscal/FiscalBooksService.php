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

    private const REVIEW_FILTERS = [
        'ALL' => 'Todos',
        'APPROVED' => 'Aprobados',
        'OBSERVED' => 'Observados',
        'REJECTED' => 'Rechazados',
        'VOIDED' => 'Anulados',
    ];

    public function build(
        int $companyId,
        FiscalDocumentDirection $direction,
        array $filters,
        ?int $perPage = null,
    ): array {
        $baseQuery = $this->baseQuery($companyId, $direction, $filters);
        $query = $this->applyReviewFilter(clone $baseQuery, $filters['review_status'] ?? 'APPROVED');
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

        $documentsQuery = (clone $query)->with(['accountingPeriod', 'journalEntry', 'felDocument:id,status'])->orderBy('document_date')->orderBy('id');
        $documents = $perPage
            ? $documentsQuery->paginate($perPage)->withQueryString()
            : $documentsQuery->get();

        return [
            'documents' => $documents,
            'count' => $count,
            'shownCount' => (clone $query)->count(),
            'statusCounts' => collect(array_keys(self::REVIEW_FILTERS))->mapWithKeys(fn (string $status) => [
                $status => (clone $this->applyReviewFilter(clone $baseQuery, $status))->count(),
            ])->all(),
            'reviewStatusLabel' => self::REVIEW_FILTERS[$filters['review_status'] ?? 'APPROVED'],
            'totals' => collect($totals)->map(fn (int $cents) => $this->fromCents($cents))->all(),
            'categoryTotals' => collect($categories)->map(fn (array $category) => [
                ...$category,
                'total_amount' => $this->fromCents($category['total_amount']),
            ])->all(),
            'filters' => $filters,
            ...$this->periodInformation($companyId, $filters),
        ];
    }

    public function options(int $companyId): array
    {
        return [
            'periods' => AccountingPeriod::where('company_id', $companyId)->orderByDesc('start_date')->get(),
            'types' => FiscalDocumentType::cases(),
            'categories' => FiscalTaxCategory::cases(),
            'reviewFilters' => self::REVIEW_FILTERS,
        ];
    }

    public function resolveDefaultPeriodId(int $companyId, ?int $sessionPeriodId = null): ?int
    {
        if ($sessionPeriodId && AccountingPeriod::where('company_id', $companyId)->whereKey($sessionPeriodId)->exists()) {
            return $sessionPeriodId;
        }

        $latestDate = FiscalDocument::forCompany($companyId)->max('document_date');
        if ($latestDate) {
            $periodId = AccountingPeriod::where('company_id', $companyId)
                ->whereDate('start_date', '<=', $latestDate)
                ->whereDate('end_date', '>=', $latestDate)
                ->orderByDesc('start_date')
                ->value('id');
            if ($periodId) {
                return (int) $periodId;
            }
        }

        $openPeriodId = AccountingPeriod::where('company_id', $companyId)
            ->where('status', 'open')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->value('id');

        $periodId = $openPeriodId
            ?: AccountingPeriod::where('company_id', $companyId)->latest('id')->value('id');

        return $periodId ? (int) $periodId : null;
    }

    private function baseQuery(int $companyId, FiscalDocumentDirection $direction, array $filters): Builder
    {
        return FiscalDocument::query()
            ->forCompany($companyId)
            ->where('direction', $direction->value)
            ->betweenDates($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->forPeriod(isset($filters['accounting_period_id']) ? (int) $filters['accounting_period_id'] : null)
            ->when($filters['document_type'] ?? null, fn ($query, $value) => $query->where('document_type', $value))
            ->when($filters['tax_category'] ?? null, fn ($query, $value) => $query->where('tax_category', $value))
            ->when($filters['third_party_tax_id'] ?? null, fn ($query, $value) => $query->where('third_party_tax_id', 'like', "%{$value}%"))
            ->when($filters['third_party_name'] ?? null, fn ($query, $value) => $query->where('third_party_name', 'like', "%{$value}%"))
            ->when($filters['series'] ?? null, fn ($query, $value) => $query->where('series', 'like', "%{$value}%"))
            ->when($filters['document_number'] ?? null, fn ($query, $value) => $query->where('document_number', 'like', "%{$value}%"))
            ->when($filters['authorization_uuid'] ?? null, fn ($query, $value) => $query->where('authorization_uuid', 'like', "%{$value}%"))
            ->when(isset($filters['grants_tax_credit']) && $filters['grants_tax_credit'] !== '', fn ($query) => $query->where('grants_tax_credit', (bool) $filters['grants_tax_credit']))
            ->when(isset($filters['is_small_taxpayer']) && $filters['is_small_taxpayer'] !== '', fn ($query) => $query->where('is_small_taxpayer', (bool) $filters['is_small_taxpayer']));
    }

    private function applyReviewFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'APPROVED' => $query
                ->where('fiscal_documents.status', FiscalDocumentStatus::ACTIVE->value)
                ->where(fn (Builder $query) => $query
                    ->whereDoesntHave('felDocument')
                    ->orWhereHas('felDocument', fn (Builder $query) => $query->where('status', 'APPROVED'))),
            'OBSERVED', 'REJECTED' => $query
                ->where('fiscal_documents.status', FiscalDocumentStatus::ACTIVE->value)
                ->whereHas('felDocument', fn (Builder $query) => $query->where('status', $status)),
            'VOIDED' => $query->where('fiscal_documents.status', FiscalDocumentStatus::VOIDED->value),
            default => $query,
        };
    }

    private function periodInformation(int $companyId, array $filters): array
    {
        if (! empty($filters['accounting_period_id'])) {
            $period = AccountingPeriod::where('company_id', $companyId)->find($filters['accounting_period_id']);
            if ($period) {
                return [
                    'periodName' => $period->name,
                    'periodRange' => $period->start_date->format('d/m/Y').' al '.$period->end_date->format('d/m/Y'),
                    'periodDescription' => 'Período: '.$period->name.' · Del '.$period->start_date->format('d/m/Y').' al '.$period->end_date->format('d/m/Y'),
                ];
            }
        }
        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            return ['periodName' => 'Rango personalizado', 'periodRange' => $filters['date_from'].' al '.$filters['date_to'], 'periodDescription' => $filters['date_from'].' al '.$filters['date_to']];
        }

        $description = ! empty($filters['date_from']) ? 'Desde '.$filters['date_from'] : (! empty($filters['date_to']) ? 'Hasta '.$filters['date_to'] : 'Sin período contable disponible');

        return ['periodName' => 'Sin período', 'periodRange' => $description, 'periodDescription' => $description];
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
