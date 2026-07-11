<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DailyBookController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) session('company_id');

        if (! $companyId) {
            return redirect()
                ->route('companies.index')
                ->with('error', 'Primero debes seleccionar una empresa activa.');
        }

        $hasCompanyAccess = $request->user()
            ->companies()
            ->where('companies.id', $companyId)
            ->wherePivot('is_active', true)
            ->exists();

        abort_unless($hasCompanyAccess, 403);

        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'accounting_period_id' => [
                'nullable',
                'integer',
                Rule::exists('accounting_periods', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'number' => ['nullable', 'integer', 'min:1'],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'search' => ['nullable', 'string', 'max:255'],
        ], [
            'date_from.date' => 'La fecha inicial no es válida.',
            'date_to.date' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'accounting_period_id.integer' => 'El período contable seleccionado no es válido.',
            'accounting_period_id.exists' => 'El período contable seleccionado no pertenece a la empresa activa.',
            'number.integer' => 'El número de póliza debe ser un número entero.',
            'number.min' => 'El número de póliza debe ser mayor que cero.',
            'account_id.integer' => 'La cuenta seleccionada no es válida.',
            'account_id.exists' => 'La cuenta seleccionada no pertenece a la empresa activa.',
            'search.max' => 'El texto de búsqueda no puede exceder 255 caracteres.',
        ]);

        $periods = AccountingPeriod::where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->get();

        $accounts = Account::where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        if ($this->shouldUseDefaultPeriod($filters)) {
            $defaultPeriod = $periods->firstWhere('status', AccountingPeriodStatus::OPEN)
                ?? $periods->first();

            if ($defaultPeriod) {
                $filters['accounting_period_id'] = $defaultPeriod->id;
            }
        }

        $query = JournalEntry::query()
            ->where('company_id', $companyId)
            ->where('status', JournalEntryStatus::POSTED->value);

        $this->applyFilters($query, $filters);

        $filteredEntryIds = (clone $query)->select('journal_entries.id');

        $generalTotals = JournalEntryLine::whereIn('journal_entry_id', $filteredEntryIds)
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $totalDebit = (string) ($generalTotals->total_debit ?? '0.00');
        $totalCredit = (string) ($generalTotals->total_credit ?? '0.00');
        $isBalanced = $this->amountToCents($totalDebit) === $this->amountToCents($totalCredit);

        $journalEntries = $query
            ->with(['accountingPeriod', 'lines.account'])
            ->withSum('lines as total_debit', 'debit')
            ->withSum('lines as total_credit', 'credit')
            ->orderBy('entry_date')
            ->orderBy('number')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return view('accounting.daily_book.index', compact(
            'journalEntries',
            'periods',
            'accounts',
            'filters',
            'totalDebit',
            'totalCredit',
            'isBalanced'
        ));
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query
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

    private function shouldUseDefaultPeriod(array $filters): bool
    {
        return ! isset($filters['date_from'])
            && ! isset($filters['date_to'])
            && ! isset($filters['accounting_period_id'])
            && ! isset($filters['number'])
            && ! isset($filters['account_id'])
            && ! isset($filters['search']);
    }

    private function amountToCents(string $amount): int
    {
        [$whole, $decimals] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($decimals, 0, 2), 2, '0');
    }
}
