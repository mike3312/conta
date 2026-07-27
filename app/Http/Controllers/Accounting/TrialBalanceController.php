<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountNature;
use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntryLine;
use App\Services\Reports\AccountingExcelExporter;
use App\Services\Reports\AccountingPdfExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TrialBalanceController extends Controller
{
    public function __construct(
        private readonly AccountingPdfExporter $pdfExporter,
        private readonly AccountingExcelExporter $excelExporter,
    ) {}

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
            'account_type' => ['nullable', Rule::enum(AccountType::class)],
            'account_from_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'account_to_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'search' => ['nullable', 'string', 'max:255'],
            'show_without_movements' => ['nullable', 'boolean'],
            'only_with_balance' => ['nullable', 'boolean'],
        ], [
            'date_from.date' => 'La fecha inicial no es válida.',
            'date_to.date' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'accounting_period_id.integer' => 'El período contable seleccionado no es válido.',
            'accounting_period_id.exists' => 'El período contable seleccionado no pertenece a la empresa activa.',
            'account_type' => 'El tipo de cuenta seleccionado no es válido.',
            'account_from_id.integer' => 'La cuenta inicial seleccionada no es válida.',
            'account_from_id.exists' => 'La cuenta inicial no pertenece a la empresa activa.',
            'account_to_id.integer' => 'La cuenta final seleccionada no es válida.',
            'account_to_id.exists' => 'La cuenta final no pertenece a la empresa activa.',
            'search.max' => 'El texto de búsqueda no puede exceder 255 caracteres.',
            'show_without_movements.boolean' => 'La opción para mostrar cuentas sin movimientos no es válida.',
            'only_with_balance.boolean' => 'La opción para mostrar únicamente cuentas con saldo no es válida.',
        ]);

        $filters['show_without_movements'] = $request->boolean('show_without_movements');
        $filters['only_with_balance'] = $request->boolean('only_with_balance');

        $company = Company::findOrFail($companyId);
        $periods = AccountingPeriod::where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->get();
        $accountOptions = Account::where('company_id', $companyId)
            ->orderBy('code')
            ->get();

        $this->applyDefaultPeriod($filters, $periods);
        $this->applyPeriodDates($filters, $periods);
        $this->validateAccountRange($filters, $accountOptions);

        $previousTotals = $this->accountTotalsQuery($companyId, $filters, true);
        $rangeTotals = $this->accountTotalsQuery($companyId, $filters, false);

        $accountsQuery = Account::query()
            ->where('accounts.company_id', $companyId)
            ->where('accounts.allows_entries', true)
            ->leftJoinSub($previousTotals, 'previous_totals', function ($join) {
                $join->on('previous_totals.account_id', '=', 'accounts.id');
            })
            ->leftJoinSub($rangeTotals, 'range_totals', function ($join) {
                $join->on('range_totals.account_id', '=', 'accounts.id');
            })
            ->select('accounts.*')
            ->selectRaw('COALESCE(previous_totals.total_debit, 0) as previous_debit_total')
            ->selectRaw('COALESCE(previous_totals.total_credit, 0) as previous_credit_total')
            ->selectRaw('COALESCE(range_totals.total_debit, 0) as range_debit_total')
            ->selectRaw('COALESCE(range_totals.total_credit, 0) as range_credit_total');

        $this->applyAccountFilters($accountsQuery, $filters, $accountOptions);
        $this->applyVisibilityFilters($accountsQuery, $filters);

        $generalTotals = $this->getGeneralTotals(clone $accountsQuery);
        $balanceStatus = $this->buildBalanceStatus($generalTotals);

        $accountsQuery->orderBy('accounts.code');
        $trialBalance = $request->attributes->getBoolean('exporting')
            ? $accountsQuery->get()
            : $accountsQuery->paginate(25)->withQueryString();

        $trialCollection = $trialBalance instanceof LengthAwarePaginator
            ? $trialBalance->getCollection()
            : $trialBalance;
        $preparedRows = $trialCollection->map(fn (Account $account) => $this->buildAccountRow($account));
        if ($trialBalance instanceof LengthAwarePaginator) {
            $trialBalance->setCollection($preparedRows);
        } else {
            $trialBalance = $preparedRows;
        }

        return view('accounting.trial_balance.index', [
            'trialBalance' => $trialBalance,
            'generalTotals' => $generalTotals,
            'balanceStatus' => $balanceStatus,
            'periods' => $periods,
            'accountOptions' => $accountOptions,
            'accountTypes' => AccountType::cases(),
            'filters' => $filters,
            'currency' => $company->currency,
        ]);
    }

    public function exportPdf(Request $request)
    {
        $company = $this->activeCompany($request);
        abort_unless($company, 403);
        $request->attributes->set('exporting', true);
        $data = $this->index($request)->getData();

        return $this->pdfExporter->download('accounting.trial_balance.exports.pdf', 'balance-comprobacion', $company, $data, now($company->timezone), 'landscape');
    }

    public function exportExcel(Request $request)
    {
        $company = $this->activeCompany($request);
        abort_unless($company, 403);
        $request->attributes->set('exporting', true);

        return $this->excelExporter->trialBalance($company, $this->index($request)->getData(), now($company->timezone));
    }

    private function activeCompany(Request $request): ?Company
    {
        $companyId = (int) session('company_id');

        return $companyId ? $request->user()->companies()->active()->wherePivot('is_active', true)->whereKey($companyId)->first() : null;
    }

    private function accountTotalsQuery(int $companyId, array $filters, bool $previous)
    {
        $query = JournalEntryLine::query()
            ->selectRaw(
                'journal_entry_lines.account_id, SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit'
            )
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', JournalEntryStatus::POSTED->value)
            ->groupBy('journal_entry_lines.account_id');

        if ($previous) {
            if (isset($filters['date_from'])) {
                $query->whereDate('journal_entries.entry_date', '<', $filters['date_from']);
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $query
                ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('journal_entries.entry_date', '>=', $date))
                ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('journal_entries.entry_date', '<=', $date));
        }

        return $query;
    }

    private function applyDefaultPeriod(array &$filters, Collection $periods): void
    {
        if (isset($filters['date_from']) || isset($filters['date_to']) || isset($filters['accounting_period_id'])) {
            return;
        }

        $defaultPeriod = $periods->firstWhere('status', AccountingPeriodStatus::OPEN)
            ?? $periods->first();

        if ($defaultPeriod) {
            $filters['accounting_period_id'] = $defaultPeriod->id;
        }
    }

    private function applyPeriodDates(array &$filters, Collection $periods): void
    {
        if (! isset($filters['accounting_period_id'])) {
            return;
        }

        $period = $periods->firstWhere('id', (int) $filters['accounting_period_id']);
        $filters['date_from'] = $period->start_date->format('Y-m-d');
        $filters['date_to'] = $period->end_date->format('Y-m-d');
    }

    private function validateAccountRange(array $filters, Collection $accounts): void
    {
        if (! isset($filters['account_from_id'], $filters['account_to_id'])) {
            return;
        }

        $from = $accounts->firstWhere('id', (int) $filters['account_from_id']);
        $to = $accounts->firstWhere('id', (int) $filters['account_to_id']);

        if (strcmp($from->code, $to->code) > 0) {
            throw ValidationException::withMessages([
                'account_to_id' => 'La cuenta final debe ser igual o posterior a la cuenta inicial.',
            ]);
        }
    }

    private function applyAccountFilters(Builder $query, array $filters, Collection $accounts): void
    {
        $query
            ->when($filters['account_type'] ?? null, fn ($query, $type) => $query->where('accounts.account_type', $type))
            ->when($filters['account_from_id'] ?? null, function ($query, $accountId) use ($accounts) {
                $query->where('accounts.code', '>=', $accounts->firstWhere('id', (int) $accountId)->code);
            })
            ->when($filters['account_to_id'] ?? null, function ($query, $accountId) use ($accounts) {
                $query->where('accounts.code', '<=', $accounts->firstWhere('id', (int) $accountId)->code);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('accounts.code', 'like', "%{$search}%")
                        ->orWhere('accounts.name', 'like', "%{$search}%");
                });
            });
    }

    private function applyVisibilityFilters(Builder $query, array $filters): void
    {
        if (! $filters['show_without_movements']) {
            $query->whereRaw(
                '(COALESCE(previous_totals.total_debit, 0) + COALESCE(previous_totals.total_credit, 0) + COALESCE(range_totals.total_debit, 0) + COALESCE(range_totals.total_credit, 0)) > 0'
            );
        }

        if ($filters['only_with_balance']) {
            $query->whereRaw(
                'ABS((COALESCE(previous_totals.total_debit, 0) - COALESCE(previous_totals.total_credit, 0)) + COALESCE(range_totals.total_debit, 0) - COALESCE(range_totals.total_credit, 0)) >= 0.01'
            );
        }
    }

    private function getGeneralTotals(Builder $accountsQuery): object
    {
        return DB::query()
            ->fromSub($accountsQuery, 'trial_accounts')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN previous_debit_total - previous_credit_total > 0 THEN previous_debit_total - previous_credit_total ELSE 0 END), 0) as previous_debit'
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN previous_debit_total - previous_credit_total < 0 THEN previous_credit_total - previous_debit_total ELSE 0 END), 0) as previous_credit'
            )
            ->selectRaw('COALESCE(SUM(range_debit_total), 0) as range_debit')
            ->selectRaw('COALESCE(SUM(range_credit_total), 0) as range_credit')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN previous_debit_total - previous_credit_total + range_debit_total - range_credit_total > 0 THEN previous_debit_total - previous_credit_total + range_debit_total - range_credit_total ELSE 0 END), 0) as final_debit'
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN previous_debit_total - previous_credit_total + range_debit_total - range_credit_total < 0 THEN previous_credit_total - previous_debit_total + range_credit_total - range_debit_total ELSE 0 END), 0) as final_credit'
            )
            ->first();
    }

    private function buildAccountRow(Account $account): array
    {
        $previousBalance = $this->amountToCents((string) $account->previous_debit_total)
            - $this->amountToCents((string) $account->previous_credit_total);
        $rangeDebit = $this->amountToCents((string) $account->range_debit_total);
        $rangeCredit = $this->amountToCents((string) $account->range_credit_total);
        $finalBalance = $previousBalance + $rangeDebit - $rangeCredit;

        return [
            'account' => $account,
            'previous_debit' => $this->centsToAmount(max($previousBalance, 0)),
            'previous_credit' => $this->centsToAmount(abs(min($previousBalance, 0))),
            'range_debit' => $this->centsToAmount($rangeDebit),
            'range_credit' => $this->centsToAmount($rangeCredit),
            'final_debit' => $this->centsToAmount(max($finalBalance, 0)),
            'final_credit' => $this->centsToAmount(abs(min($finalBalance, 0))),
            'is_normal_balance' => $this->isNormalBalance($account->nature, $finalBalance),
        ];
    }

    private function buildBalanceStatus(object $totals): array
    {
        $movementDifference = abs(
            $this->amountToCents((string) $totals->range_debit)
            - $this->amountToCents((string) $totals->range_credit)
        );
        $finalDifference = abs(
            $this->amountToCents((string) $totals->final_debit)
            - $this->amountToCents((string) $totals->final_credit)
        );

        return [
            'is_balanced' => $movementDifference <= 1 && $finalDifference <= 1,
            'movement_difference' => $this->centsToAmount($movementDifference),
            'final_difference' => $this->centsToAmount($finalDifference),
        ];
    }

    private function isNormalBalance(AccountNature $nature, int $balance): bool
    {
        return $balance === 0
            || ($nature === AccountNature::DEBIT && $balance > 0)
            || ($nature === AccountNature::CREDIT && $balance < 0);
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
