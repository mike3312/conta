<?php

namespace App\Services\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JournalEntryService
{
    public function createDraft(array $data, int $companyId, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($data, $companyId, $userId) {
            $period = $this->getOpenPeriod($data['accounting_period_id'], $companyId, true);
            $this->ensureDateWithinPeriod($data['entry_date'], $period);
            $lines = $this->validateAndNormalizeLines($data['lines'], $companyId);

            $journalEntry = JournalEntry::create([
                'company_id' => $companyId,
                'accounting_period_id' => $data['accounting_period_id'],
                'number' => null,
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'status' => JournalEntryStatus::DRAFT->value,
                'created_by' => $userId,
                'updated_by' => null,
            ]);

            $this->replaceLines($journalEntry, $lines);

            return $journalEntry->load('lines.account');
        });
    }

    public function updateDraft(
        JournalEntry $journalEntry,
        array $data,
        int $companyId,
        int $userId
    ): JournalEntry {
        return DB::transaction(function () use ($journalEntry, $data, $companyId, $userId) {
            $journalEntry = $this->getLockedEntry($journalEntry->id, $companyId);
            $this->ensureDraft($journalEntry);
            $period = $this->getOpenPeriod($data['accounting_period_id'], $companyId, true);
            $this->ensureDateWithinPeriod($data['entry_date'], $period);
            $lines = $this->validateAndNormalizeLines($data['lines'], $companyId);

            $journalEntry->update([
                'accounting_period_id' => $data['accounting_period_id'],
                'entry_date' => $data['entry_date'],
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'updated_by' => $userId,
            ]);

            $this->replaceLines($journalEntry, $lines);

            return $journalEntry->load('lines.account');
        });
    }

    public function post(JournalEntry $journalEntry, int $companyId, int $userId): JournalEntry
    {
        return DB::transaction(function () use ($journalEntry, $companyId, $userId) {
            $journalEntry = $this->getLockedEntry($journalEntry->id, $companyId);
            $this->ensureDraft($journalEntry);

            $period = $this->getOpenPeriod($journalEntry->accounting_period_id, $companyId, true);
            $this->ensureDateWithinPeriod($journalEntry->entry_date->format('Y-m-d'), $period);

            $lines = $journalEntry->lines()->orderBy('line_order')->get()->toArray();
            $normalizedLines = $this->validateAndNormalizeLines($lines, $companyId);
            $this->validateBalancedEntry($normalizedLines);

            $nextNumber = ((int) JournalEntry::where('company_id', $companyId)
                ->where('accounting_period_id', $period->id)
                ->whereNotNull('number')
                ->max('number')) + 1;

            $journalEntry->update([
                'number' => $nextNumber,
                'status' => JournalEntryStatus::POSTED->value,
                'posted_by' => $userId,
                'posted_at' => now(),
                'updated_by' => $userId,
            ]);

            return $journalEntry->fresh(['accountingPeriod', 'lines.account', 'creator', 'postedBy']);
        });
    }

    public function void(
        JournalEntry $journalEntry,
        string $reason,
        int $companyId,
        int $userId
    ): JournalEntry {
        return DB::transaction(function () use ($journalEntry, $reason, $companyId, $userId) {
            $journalEntry = $this->getLockedEntry($journalEntry->id, $companyId);

            if ($journalEntry->status !== JournalEntryStatus::POSTED) {
                throw ValidationException::withMessages([
                    'journal_entry' => 'Solo se puede anular una póliza contabilizada.',
                ]);
            }

            $this->getOpenPeriod($journalEntry->accounting_period_id, $companyId, true);

            $journalEntry->update([
                'status' => JournalEntryStatus::VOIDED->value,
                'voided_by' => $userId,
                'voided_at' => now(),
                'void_reason' => $reason,
                'updated_by' => $userId,
            ]);

            return $journalEntry->fresh(['accountingPeriod', 'lines.account', 'creator', 'postedBy', 'voidedBy']);
        });
    }

    public function deleteDraft(JournalEntry $journalEntry, int $companyId): void
    {
        DB::transaction(function () use ($journalEntry, $companyId) {
            $journalEntry = $this->getLockedEntry($journalEntry->id, $companyId);
            $this->ensureDraft($journalEntry);
            $journalEntry->delete();
        });
    }

    private function getLockedEntry(int $journalEntryId, int $companyId): JournalEntry
    {
        return JournalEntry::where('company_id', $companyId)
            ->lockForUpdate()
            ->findOrFail($journalEntryId);
    }

    private function getOpenPeriod(int $periodId, int $companyId, bool $lock = false): AccountingPeriod
    {
        $query = AccountingPeriod::where('company_id', $companyId)
            ->whereKey($periodId);

        if ($lock) {
            $query->lockForUpdate();
        }

        $period = $query->first();

        if (! $period) {
            throw ValidationException::withMessages([
                'accounting_period_id' => 'El período contable seleccionado no pertenece a la empresa activa.',
            ]);
        }

        if ($period->status !== AccountingPeriodStatus::OPEN) {
            throw ValidationException::withMessages([
                'accounting_period_id' => 'El período contable seleccionado está cerrado.',
            ]);
        }

        return $period;
    }

    private function ensureDateWithinPeriod(string $entryDate, AccountingPeriod $period): void
    {
        if ($entryDate < $period->start_date->format('Y-m-d') || $entryDate > $period->end_date->format('Y-m-d')) {
            throw ValidationException::withMessages([
                'entry_date' => 'La fecha de la póliza debe estar dentro del período contable seleccionado.',
            ]);
        }
    }

    private function validateAndNormalizeLines(array $lines, int $companyId): array
    {
        $accountIds = collect($lines)
            ->pluck('account_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $accounts = Account::where('company_id', $companyId)
            ->whereIn('id', $accountIds)
            ->get()
            ->keyBy('id');

        $normalized = [];

        foreach (array_values($lines) as $index => $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $account = $accounts->get($accountId);

            if (! $account) {
                throw ValidationException::withMessages([
                    "lines.$index.account_id" => 'La cuenta seleccionada no pertenece a la empresa activa.',
                ]);
            }

            if (! $account->is_active) {
                throw ValidationException::withMessages([
                    "lines.$index.account_id" => 'La cuenta seleccionada está inactiva.',
                ]);
            }

            if (! $account->allows_entries) {
                throw ValidationException::withMessages([
                    "lines.$index.account_id" => 'La cuenta seleccionada no permite movimientos contables.',
                ]);
            }

            $debitCents = $this->amountToCents((string) ($line['debit'] ?? '0'));
            $creditCents = $this->amountToCents((string) ($line['credit'] ?? '0'));

            if ($debitCents < 0 || $creditCents < 0) {
                throw ValidationException::withMessages([
                    "lines.$index.debit" => 'Los importes no pueden ser negativos.',
                ]);
            }

            if ($debitCents > 0 && $creditCents > 0) {
                throw ValidationException::withMessages([
                    "lines.$index.debit" => 'Una línea no puede tener Debe y Haber al mismo tiempo.',
                ]);
            }

            $normalized[] = [
                'account_id' => $accountId,
                'description' => $line['description'] ?? null,
                'debit' => $this->centsToAmount($debitCents),
                'credit' => $this->centsToAmount($creditCents),
                'debit_cents' => $debitCents,
                'credit_cents' => $creditCents,
            ];
        }

        return $normalized;
    }

    private function validateBalancedEntry(array $lines): void
    {
        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'La póliza debe tener al menos dos líneas para contabilizarse.',
            ]);
        }

        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($lines as $index => $line) {
            if ($line['debit_cents'] === 0 && $line['credit_cents'] === 0) {
                throw ValidationException::withMessages([
                    "lines.$index.debit" => 'Cada línea debe tener un importe positivo en Debe o Haber.',
                ]);
            }

            $totalDebit += $line['debit_cents'];
            $totalCredit += $line['credit_cents'];
        }

        if ($totalDebit <= 0 || $totalCredit <= 0) {
            throw ValidationException::withMessages([
                'lines' => 'El total de la póliza debe ser mayor que cero.',
            ]);
        }

        if ($totalDebit !== $totalCredit) {
            throw ValidationException::withMessages([
                'lines' => 'La suma del Debe debe ser exactamente igual a la suma del Haber.',
            ]);
        }
    }

    private function replaceLines(JournalEntry $journalEntry, array $lines): void
    {
        $journalEntry->lines()->delete();

        foreach ($lines as $index => $line) {
            $journalEntry->lines()->create([
                'account_id' => $line['account_id'],
                'description' => $line['description'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'line_order' => $index + 1,
            ]);
        }
    }

    private function ensureDraft(JournalEntry $journalEntry): void
    {
        if ($journalEntry->status !== JournalEntryStatus::DRAFT) {
            throw ValidationException::withMessages([
                'journal_entry' => 'Solo las pólizas en borrador pueden modificarse o contabilizarse.',
            ]);
        }
    }

    private function amountToCents(string $amount): int
    {
        $amount = trim($amount);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches)) {
            throw ValidationException::withMessages([
                'lines' => 'Uno de los importes contables no es válido.',
            ]);
        }

        $whole = (int) $matches[2];
        $decimals = str_pad($matches[3] ?? '', 2, '0');
        $cents = ($whole * 100) + (int) $decimals;

        return ($matches[1] ?? '') === '-' ? -$cents : $cents;
    }

    private function centsToAmount(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return $sign.intdiv($absolute, 100).'.'.str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }
}
