<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountType;
use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntryLine;
use App\Services\Reports\AccountingExcelExporter;
use App\Services\Reports\AccountingPdfExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class IncomeStatementController extends Controller
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
            'search' => ['nullable', 'string', 'max:255'],
            'show_without_movements' => ['nullable', 'boolean'],
            'show_details' => ['nullable', 'boolean'],
        ], [
            'date_from.date' => 'La fecha inicial no es válida.',
            'date_to.date' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'accounting_period_id.integer' => 'El período contable seleccionado no es válido.',
            'accounting_period_id.exists' => 'El período contable seleccionado no pertenece a la empresa activa.',
            'search.max' => 'El texto de búsqueda no puede exceder 255 caracteres.',
            'show_without_movements.boolean' => 'La opción para mostrar cuentas sin movimientos no es válida.',
            'show_details.boolean' => 'La opción para mostrar el detalle no es válida.',
        ]);

        $filters['show_without_movements'] = $request->boolean('show_without_movements');
        $filters['show_details'] = $request->query->count() === 0 || $request->boolean('show_details');

        $company = Company::findOrFail($companyId);
        $periods = AccountingPeriod::where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->get();

        $this->applyDefaultPeriod($filters, $periods);
        $this->applyPeriodDates($filters, $periods);

        $movementTotals = $this->movementTotalsQuery($companyId, $filters);

        $accounts = Account::query()
            ->where('accounts.company_id', $companyId)
            ->whereIn('accounts.account_type', [
                AccountType::INCOME->value,
                AccountType::EXPENSE->value,
            ])
            ->leftJoinSub($movementTotals, 'movement_totals', function ($join) {
                $join->on('movement_totals.account_id', '=', 'accounts.id');
            })
            ->select('accounts.*')
            ->selectRaw('COALESCE(movement_totals.total_debit, 0) as range_debit_total')
            ->selectRaw('COALESCE(movement_totals.total_credit, 0) as range_credit_total')
            ->orderBy('accounts.code')
            ->get();

        $accounts = $this->filterAccounts($accounts, $filters);

        $incomeAccounts = $accounts->where('account_type', AccountType::INCOME);
        $expenseAccounts = $accounts->where('account_type', AccountType::EXPENSE);

        $incomeSection = $this->buildSection($incomeAccounts, AccountType::INCOME);
        $expenseSection = $this->buildSection($expenseAccounts, AccountType::EXPENSE);

        $totalIncomeCents = $this->sectionNetCents($incomeAccounts, AccountType::INCOME);
        $totalExpenseCents = $this->sectionNetCents($expenseAccounts, AccountType::EXPENSE);
        $resultCents = $totalIncomeCents - $totalExpenseCents;

        return view('accounting.income_statement.index', [
            'company' => $company,
            'periods' => $periods,
            'filters' => $filters,
            'incomeSection' => $incomeSection,
            'expenseSection' => $expenseSection,
            'totalIncome' => $this->centsToAmount(abs($totalIncomeCents)),
            'totalExpense' => $this->centsToAmount(abs($totalExpenseCents)),
            'incomeIsContrary' => $totalIncomeCents < 0,
            'expenseIsContrary' => $totalExpenseCents < 0,
            'result' => $this->centsToAmount(abs($resultCents)),
            'resultType' => $this->resultType($resultCents),
            'hasInformation' => $incomeAccounts->isNotEmpty() || $expenseAccounts->isNotEmpty(),
        ]);
    }

    public function exportPdf(Request $request)
    {
        $company = $this->activeCompany($request);
        abort_unless($company, 403);

        return $this->pdfExporter->download('accounting.income_statement.exports.pdf', 'estado-resultados', $company, $this->index($request)->getData(), now($company->timezone));
    }

    public function exportExcel(Request $request)
    {
        $company = $this->activeCompany($request);
        abort_unless($company, 403);

        return $this->excelExporter->incomeStatement($company, $this->index($request)->getData(), now($company->timezone));
    }

    private function activeCompany(Request $request): ?Company
    {
        $companyId = (int) session('company_id');

        return $companyId ? $request->user()->companies()->active()->wherePivot('is_active', true)->whereKey($companyId)->first() : null;
    }

    private function movementTotalsQuery(int $companyId, array $filters)
    {
        return JournalEntryLine::query()
            ->selectRaw(
                'journal_entry_lines.account_id, SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit'
            )
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', JournalEntryStatus::POSTED->value)
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('journal_entries.entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('journal_entries.entry_date', '<=', $date))
            ->groupBy('journal_entry_lines.account_id');
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

    private function filterAccounts(Collection $accounts, array $filters): Collection
    {
        $selectedIds = $accounts->pluck('id')->flip();

        if (isset($filters['search'])) {
            $matchingIds = $accounts
                ->filter(fn (Account $account) => str_contains(mb_strtolower($account->code), mb_strtolower($filters['search']))
                    || str_contains(mb_strtolower($account->name), mb_strtolower($filters['search'])))
                ->pluck('id')
                ->flip();

            $selectedIds = $this->expandHierarchySelection($accounts, $matchingIds);
        }

        if (! $filters['show_without_movements']) {
            $activeIds = $accounts
                ->filter(fn (Account $account) => $this->accountHasMovements($account))
                ->pluck('id')
                ->flip();

            $activeIds = $this->includeAncestors($accounts, $activeIds);
            $selectedIds = $selectedIds->intersectByKeys($activeIds);
        }

        return $accounts
            ->filter(fn (Account $account) => $selectedIds->has($account->id))
            ->values();
    }

    private function expandHierarchySelection(Collection $accounts, Collection $matchingIds): Collection
    {
        $selected = collect($matchingIds->all());
        $added = true;

        while ($added) {
            $added = false;

            foreach ($accounts as $account) {
                if ($account->parent_id && $selected->has($account->parent_id) && ! $selected->has($account->id)) {
                    $selected->put($account->id, true);
                    $added = true;
                }
            }
        }

        return $this->includeAncestors($accounts, $selected);
    }

    private function includeAncestors(Collection $accounts, Collection $selectedIds): Collection
    {
        $accountsById = $accounts->keyBy('id');

        foreach ($selectedIds->keys()->all() as $accountId) {
            $account = $accountsById->get($accountId);

            while ($account?->parent_id && $accountsById->has($account->parent_id)) {
                $selectedIds->put($account->parent_id, true);
                $account = $accountsById->get($account->parent_id);
            }
        }

        return $selectedIds;
    }

    private function buildSection(Collection $accounts, AccountType $type): array
    {
        $accountIds = $accounts->pluck('id')->flip();
        $roots = $accounts->filter(fn (Account $account) => ! $account->parent_id || ! $accountIds->has($account->parent_id));

        return $roots
            ->map(fn (Account $account) => $this->buildNode($account, $accounts, $type))
            ->values()
            ->all();
    }

    private function buildNode(Account $account, Collection $accounts, AccountType $type): array
    {
        $children = $accounts
            ->where('parent_id', $account->id)
            ->map(fn (Account $child) => $this->buildNode($child, $accounts, $type))
            ->values();

        $directDebitCents = $this->amountToCents((string) $account->range_debit_total);
        $directCreditCents = $this->amountToCents((string) $account->range_credit_total);
        $directNetCents = $this->netCents($directDebitCents, $directCreditCents, $type);
        $subtotalCents = $directNetCents + $children->sum('subtotal_cents');

        return [
            'account' => $account,
            'children' => $children->all(),
            'debit' => $this->centsToAmount($directDebitCents),
            'credit' => $this->centsToAmount($directCreditCents),
            'net' => $this->centsToAmount(abs($directNetCents)),
            'is_contrary' => $directNetCents < 0,
            'subtotal' => $this->centsToAmount(abs($subtotalCents)),
            'subtotal_is_contrary' => $subtotalCents < 0,
            'subtotal_cents' => $subtotalCents,
        ];
    }

    private function sectionNetCents(Collection $accounts, AccountType $type): int
    {
        return $accounts->sum(function (Account $account) use ($type) {
            return $this->netCents(
                $this->amountToCents((string) $account->range_debit_total),
                $this->amountToCents((string) $account->range_credit_total),
                $type
            );
        });
    }

    private function accountHasMovements(Account $account): bool
    {
        return $this->amountToCents((string) $account->range_debit_total) > 0
            || $this->amountToCents((string) $account->range_credit_total) > 0;
    }

    private function netCents(int $debitCents, int $creditCents, AccountType $type): int
    {
        return $type === AccountType::INCOME
            ? $creditCents - $debitCents
            : $debitCents - $creditCents;
    }

    private function resultType(int $resultCents): string
    {
        return match (true) {
            $resultCents > 0 => 'profit',
            $resultCents < 0 => 'loss',
            default => 'zero',
        };
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
