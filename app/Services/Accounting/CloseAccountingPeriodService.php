<?php

namespace App\Services\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseAccountingPeriodService
{
    public function __construct(private AccountingBalanceAuditService $balanceAudit) {}

    public function close(AccountingPeriod $accountingPeriod, int $companyId, int $userId): AccountingPeriod
    {
        return DB::transaction(function () use ($accountingPeriod, $companyId, $userId): AccountingPeriod {
            $period = AccountingPeriod::query()
                ->lockForUpdate()
                ->findOrFail($accountingPeriod->id);

            abort_unless((int) $period->company_id === $companyId, 403);

            if ($period->status !== AccountingPeriodStatus::OPEN) {
                throw ValidationException::withMessages([
                    'accounting_period' => 'El período contable ya se encuentra cerrado.',
                ]);
            }

            if ($period->journalEntries()->where('status', JournalEntryStatus::DRAFT->value)->exists()) {
                throw ValidationException::withMessages([
                    'accounting_period' => 'No se puede cerrar el período porque contiene pólizas en borrador.',
                ]);
            }

            if ($period->journalEntries()
                ->where(function ($query) use ($period): void {
                    $query->whereDate('entry_date', '<', $period->start_date)
                        ->orWhereDate('entry_date', '>', $period->end_date);
                })
                ->exists()) {
                throw ValidationException::withMessages([
                    'accounting_period' => 'No se puede cerrar el período porque contiene pólizas fuera de sus fechas.',
                ]);
            }

            $hasUnbalancedEntries = JournalEntry::query()
                ->where('journal_entries.company_id', $companyId)
                ->where('journal_entries.accounting_period_id', $period->id)
                ->where('journal_entries.status', JournalEntryStatus::POSTED->value)
                ->leftJoin('journal_entry_lines', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
                ->select('journal_entries.id')
                ->groupBy('journal_entries.id')
                ->havingRaw('COALESCE(SUM(journal_entry_lines.debit), 0) <> COALESCE(SUM(journal_entry_lines.credit), 0)')
                ->get()
                ->isNotEmpty();

            if ($hasUnbalancedEntries) {
                throw ValidationException::withMessages([
                    'accounting_period' => 'No se puede cerrar el período porque contiene pólizas descuadradas.',
                ]);
            }

            if ($this->balanceAudit->observations($companyId, $period->end_date->toDateString())->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'accounting_period' => 'No se puede cerrar el período porque existen cuentas con saldo contrario. Revise las observaciones del Balance General.',
                ]);
            }

            $period->update([
                'status' => AccountingPeriodStatus::CLOSED->value,
                'closed_at' => now(),
                'closed_by' => $userId,
            ]);

            return $period->fresh('closedBy');
        });
    }
}
