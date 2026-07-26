<?php

namespace App\Services\Accounting;

use App\Enums\AccountNature;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use Illuminate\Support\Collection;

class AccountingBalanceAuditService
{
    public function observations(int $companyId, string $cutoffDate): Collection
    {
        return Account::query()
            ->join('journal_entry_lines', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('accounts.company_id', $companyId)
            ->where('journal_entries.company_id', $companyId)
            ->where('accounts.allows_entries', true)
            ->where('journal_entries.status', JournalEntryStatus::POSTED->value)
            ->whereDate('journal_entries.entry_date', '<=', $cutoffDate)
            ->select([
                'accounts.id',
                'accounts.code',
                'accounts.name',
                'accounts.nature',
            ])
            ->selectRaw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit')
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.nature')
            ->orderBy('accounts.code')
            ->get()
            ->map(function (Account $account): ?array {
                $debitCents = $this->amountToCents((string) $account->total_debit);
                $creditCents = $this->amountToCents((string) $account->total_credit);
                $normalBalanceCents = $account->nature === AccountNature::DEBIT
                    ? $debitCents - $creditCents
                    : $creditCents - $debitCents;

                if ($normalBalanceCents >= 0) {
                    return null;
                }

                return [
                    'account_id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'nature' => $account->nature,
                    'expected_nature' => $account->nature->label(),
                    'total_debit' => $this->centsToAmount($debitCents),
                    'total_credit' => $this->centsToAmount($creditCents),
                    'contrary_balance' => $this->centsToAmount(abs($normalBalanceCents)),
                ];
            })
            ->filter()
            ->values();
    }

    private function amountToCents(string $amount): int
    {
        [$whole, $decimals] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($decimals, 0, 2), 2, '0');
    }

    private function centsToAmount(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
