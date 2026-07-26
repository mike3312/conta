<?php

namespace App\Services\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DashboardService
{
    public function get(Company $company, array $filters = []): array
    {
        $periods = AccountingPeriod::where('company_id', $company->id)->orderByDesc('start_date')->get();
        [$dateFrom, $dateTo, $selectedPeriodId] = $this->resolveRange($periods, $filters);
        $totals = $this->financialTotals($company->id, $dateFrom, $dateTo);

        $income = $this->amount($totals->income_net);
        $expenses = $this->amount($totals->expense_net);
        $assets = $this->amount($totals->assets_net);

        return [
            'company' => $company,
            'periods' => $periods,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'accounting_period_id' => $selectedPeriodId,
            ],
            'metrics' => [
                'income' => $income,
                'expenses' => $expenses,
                'net_result' => $income - $expenses,
                'assets' => $assets,
            ],
            'chart' => $this->monthlyChart($company->id, $dateFrom, $dateTo),
            'recentEntries' => JournalEntry::query()
                ->where('company_id', $company->id)
                ->withSum('lines as total_amount', 'debit')
                ->orderByDesc('entry_date')
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
        ];
    }

    private function resolveRange($periods, array $filters): array
    {
        $selectedPeriod = isset($filters['accounting_period_id'])
            ? $periods->firstWhere('id', (int) $filters['accounting_period_id'])
            : null;

        if ($selectedPeriod) {
            return [$selectedPeriod->start_date->toDateString(), $selectedPeriod->end_date->toDateString(), $selectedPeriod->id];
        }

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $from = Carbon::parse($filters['date_from'] ?? $filters['date_to'])->toDateString();
            $to = Carbon::parse($filters['date_to'] ?? $filters['date_from'])->toDateString();

            return [$from, $to, null];
        }

        $openPeriod = $periods->firstWhere('status', AccountingPeriodStatus::OPEN);

        return $openPeriod
            ? [$openPeriod->start_date->toDateString(), $openPeriod->end_date->toDateString(), $openPeriod->id]
            : [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), null];
    }

    private function financialTotals(int $companyId, string $dateFrom, string $dateTo): object
    {
        return JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('accounts.company_id', $companyId)
            ->where('journal_entries.status', JournalEntryStatus::POSTED->value)
            ->whereDate('journal_entries.entry_date', '<=', $dateTo)
            ->selectRaw('COALESCE(SUM(CASE WHEN accounts.account_type = ? AND journal_entries.entry_date >= ? THEN journal_entry_lines.credit - journal_entry_lines.debit ELSE 0 END), 0) as income_net', [AccountType::INCOME->value, $dateFrom])
            ->selectRaw('COALESCE(SUM(CASE WHEN accounts.account_type = ? AND journal_entries.entry_date >= ? THEN journal_entry_lines.debit - journal_entry_lines.credit ELSE 0 END), 0) as expense_net', [AccountType::EXPENSE->value, $dateFrom])
            ->selectRaw('COALESCE(SUM(CASE WHEN accounts.account_type = ? THEN journal_entry_lines.debit - journal_entry_lines.credit ELSE 0 END), 0) as assets_net', [AccountType::ASSET->value])
            ->first();
    }

    private function monthlyChart(int $companyId, string $dateFrom, string $dateTo): array
    {
        $rows = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('accounts.company_id', $companyId)
            ->where('journal_entries.status', JournalEntryStatus::POSTED->value)
            ->whereBetween('journal_entries.entry_date', [$dateFrom, $dateTo])
            ->whereIn('accounts.account_type', [AccountType::INCOME->value, AccountType::EXPENSE->value])
            ->selectRaw('journal_entries.entry_date, accounts.account_type, SUM(journal_entry_lines.debit) as debit_total, SUM(journal_entry_lines.credit) as credit_total')
            ->groupBy('journal_entries.entry_date', 'accounts.account_type')
            ->get();

        $monthly = [];
        foreach ($rows as $row) {
            $key = Carbon::parse($row->entry_date)->format('Y-m');
            $net = $row->account_type === AccountType::INCOME->value
                ? $this->amount($row->credit_total) - $this->amount($row->debit_total)
                : $this->amount($row->debit_total) - $this->amount($row->credit_total);
            $monthly[$key][$row->account_type] = ($monthly[$key][$row->account_type] ?? 0) + $net;
        }

        $labels = $income = $expenses = [];
        foreach (CarbonPeriod::create(Carbon::parse($dateFrom)->startOfMonth(), '1 month', Carbon::parse($dateTo)->startOfMonth()) as $month) {
            $key = $month->format('Y-m');
            $labels[] = ucfirst($month->locale('es')->translatedFormat('M Y'));
            $income[] = round($monthly[$key][AccountType::INCOME->value] ?? 0, 2);
            $expenses[] = round($monthly[$key][AccountType::EXPENSE->value] ?? 0, 2);
        }

        return compact('labels', 'income', 'expenses');
    }

    private function amount($value): float
    {
        return round((float) ($value ?? 0), 2);
    }
}
