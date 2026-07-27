<?php

namespace App\Services\Reports;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Builder;

class DailyBookReportService
{
    private const FILTER_KEYS = [
        'date_from',
        'date_to',
        'accounting_period_id',
        'number',
        'account_id',
        'search',
    ];

    public function normalizeFilters(int $companyId, array $validatedFilters): array
    {
        $filters = array_intersect_key($validatedFilters, array_flip(self::FILTER_KEYS));

        if ($this->hasExplicitFilters($filters)) {
            return $filters;
        }

        $defaultPeriod = AccountingPeriod::where('company_id', $companyId)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [AccountingPeriodStatus::OPEN->value])
            ->orderByDesc('start_date')
            ->first();

        if ($defaultPeriod) {
            $filters['accounting_period_id'] = $defaultPeriod->id;
        }

        return $filters;
    }

    public function filterOptions(int $companyId): array
    {
        return [
            'periods' => AccountingPeriod::where('company_id', $companyId)
                ->orderByDesc('start_date')
                ->get(),
            'accounts' => Account::where('company_id', $companyId)
                ->orderBy('code')
                ->get(),
        ];
    }

    public function build(int $companyId, array $filters, ?int $perPage = null): array
    {
        $query = $this->query($companyId, $filters);
        $filteredEntryIds = (clone $query)->select('journal_entries.id');

        $totals = JournalEntryLine::whereIn('journal_entry_id', $filteredEntryIds)
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $totalDebit = (string) ($totals->total_debit ?? '0.00');
        $totalCredit = (string) ($totals->total_credit ?? '0.00');

        $entriesQuery = $query
            ->with(['accountingPeriod', 'lines.account'])
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->orderBy('entry_date')
            ->orderBy('number')
            ->orderBy('id');

        $entries = $perPage === null
            ? $entriesQuery->get()
            : $entriesQuery->paginate($perPage)->withQueryString();

        $period = isset($filters['accounting_period_id'])
            ? AccountingPeriod::where('company_id', $companyId)->find($filters['accounting_period_id'])
            : null;

        return [
            'entries' => $entries,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'isBalanced' => $this->amountToCents($totalDebit) === $this->amountToCents($totalCredit),
            'periodDescription' => $this->periodDescription($filters, $period),
        ];
    }

    private function query(int $companyId, array $filters): Builder
    {
        return JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('status', JournalEntryStatus::POSTED->value)
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('entry_date', '<=', $date))
            ->when(
                $filters['accounting_period_id'] ?? null,
                fn ($query, $periodId) => $query->where('accounting_period_id', $periodId)
            )
            ->when($filters['number'] ?? null, fn ($query, $number) => $query->where('number', $number))
            ->when($filters['account_id'] ?? null, function ($query, $accountId) {
                $query->whereHas('lines', fn ($query) => $query->where('account_id', $accountId));
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhereHas('lines', fn ($query) => $query->where('description', 'like', "%{$search}%"));
                });
            });
    }

    private function hasExplicitFilters(array $filters): bool
    {
        return collect($filters)->contains(fn ($value) => $value !== null && $value !== '');
    }

    private function periodDescription(array $filters, ?AccountingPeriod $period): string
    {
        $parts = [];

        if ($period) {
            $parts[] = 'Período: '.$period->name;
        }

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if ($dateFrom && $dateTo) {
            $parts[] = "Rango: {$dateFrom} al {$dateTo}";
        } elseif ($dateFrom) {
            $parts[] = "Desde: {$dateFrom}";
        } elseif ($dateTo) {
            $parts[] = "Hasta: {$dateTo}";
        } elseif ($period) {
            $parts[] = 'Rango: '.$period->start_date->format('Y-m-d').' al '.$period->end_date->format('Y-m-d');
        }

        return $parts ? implode(' · ', $parts) : 'Todos los períodos';
    }

    private function amountToCents(string $amount): int
    {
        [$whole, $decimals] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($decimals, 0, 2), 2, '0');
    }
}
