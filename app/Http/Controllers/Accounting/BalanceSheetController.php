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
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class BalanceSheetController extends Controller
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
            'cutoff_date' => ['nullable', 'date'],
            'accounting_period_id' => [
                'nullable',
                'integer',
                Rule::exists('accounting_periods', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'search' => ['nullable', 'string', 'max:255'],
            'show_zero_balances' => ['nullable', 'boolean'],
        ], [
            'cutoff_date.date' => 'La fecha de corte no es válida.',
            'accounting_period_id.integer' => 'El período contable seleccionado no es válido.',
            'accounting_period_id.exists' => 'El período contable seleccionado no pertenece a la empresa activa.',
            'search.max' => 'El texto de búsqueda no puede exceder 255 caracteres.',
            'show_zero_balances.boolean' => 'La opción para mostrar cuentas sin saldo no es válida.',
        ]);

        $filters['show_zero_balances'] = $request->boolean('show_zero_balances');

        $company = Company::findOrFail($companyId);
        $periods = AccountingPeriod::where('company_id', $companyId)
            ->orderByDesc('start_date')
            ->get();

        $this->applyDefaultCutoffDate($filters, $periods);
        $this->applyPeriodCutoffDate($filters, $periods);

        $accountTotals = $this->accountTotalsQuery($companyId, $filters['cutoff_date']);

        $accounts = Account::query()
            ->where('accounts.company_id', $companyId)
            ->whereIn('accounts.account_type', [
                AccountType::ASSET->value,
                AccountType::LIABILITY->value,
                AccountType::EQUITY->value,
            ])
            ->leftJoinSub($accountTotals, 'account_totals', function ($join) {
                $join->on('account_totals.account_id', '=', 'accounts.id');
            })
            ->select('accounts.*')
            ->selectRaw('COALESCE(account_totals.total_debit, 0) as accumulated_debit')
            ->selectRaw('COALESCE(account_totals.total_credit, 0) as accumulated_credit')
            ->orderBy('accounts.code')
            ->get();

        $accounts = $this->filterAccounts($accounts, $filters);

        $assetAccounts = $accounts->where('account_type', AccountType::ASSET);
        $liabilityAccounts = $accounts->where('account_type', AccountType::LIABILITY);
        $equityAccounts = $accounts->where('account_type', AccountType::EQUITY);

        $assetSection = $this->buildSection($assetAccounts, AccountType::ASSET);
        $liabilitySection = $this->buildSection($liabilityAccounts, AccountType::LIABILITY);
        $equitySection = $this->buildSection($equityAccounts, AccountType::EQUITY);

        $totalAssetsCents = $this->sectionNetCents($assetAccounts, AccountType::ASSET);
        $totalLiabilitiesCents = $this->sectionNetCents($liabilityAccounts, AccountType::LIABILITY);
        $baseEquityCents = $this->sectionNetCents($equityAccounts, AccountType::EQUITY);
        $pendingResultCents = $this->pendingResultCents($companyId, $filters['cutoff_date']);
        $totalEquityCents = $baseEquityCents + $pendingResultCents;
        $liabilitiesAndEquityCents = $totalLiabilitiesCents + $totalEquityCents;
        $differenceCents = $totalAssetsCents - $liabilitiesAndEquityCents;

        return view('accounting.balance_sheet.index', [
            'company' => $company,
            'periods' => $periods,
            'filters' => $filters,
            'assetSection' => $assetSection,
            'liabilitySection' => $liabilitySection,
            'equitySection' => $equitySection,
            'totalAssets' => $this->centsToAmount(abs($totalAssetsCents)),
            'assetsAreContrary' => $totalAssetsCents < 0,
            'totalLiabilities' => $this->centsToAmount(abs($totalLiabilitiesCents)),
            'liabilitiesAreContrary' => $totalLiabilitiesCents < 0,
            'baseEquity' => $this->centsToAmount(abs($baseEquityCents)),
            'baseEquityIsContrary' => $baseEquityCents < 0,
            'pendingResult' => $this->centsToAmount(abs($pendingResultCents)),
            'pendingResultType' => $this->resultType($pendingResultCents),
            'totalEquity' => $this->centsToAmount(abs($totalEquityCents)),
            'equityIsContrary' => $totalEquityCents < 0,
            'liabilitiesAndEquity' => $this->centsToAmount(abs($liabilitiesAndEquityCents)),
            'difference' => $this->centsToAmount(abs($differenceCents)),
            'differenceSide' => $differenceCents >= 0 ? 'Activos' : 'Pasivos y patrimonio',
            'isBalanced' => abs($differenceCents) <= 1,
            'hasInformation' => $accounts->isNotEmpty() || $pendingResultCents !== 0,
        ]);
    }

    private function accountTotalsQuery(int $companyId, string $cutoffDate)
    {
        return JournalEntryLine::query()
            ->selectRaw(
                'journal_entry_lines.account_id, SUM(journal_entry_lines.debit) as total_debit, SUM(journal_entry_lines.credit) as total_credit'
            )
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', JournalEntryStatus::POSTED->value)
            ->whereDate('journal_entries.entry_date', '<=', $cutoffDate)
            ->groupBy('journal_entry_lines.account_id');
    }

    private function pendingResultCents(int $companyId, string $cutoffDate): int
    {
        $totals = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('accounts.company_id', $companyId)
            ->where('journal_entries.status', JournalEntryStatus::POSTED->value)
            ->whereDate('journal_entries.entry_date', '<=', $cutoffDate)
            ->whereIn('accounts.account_type', [
                AccountType::INCOME->value,
                AccountType::EXPENSE->value,
            ])
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN accounts.account_type = 'income' THEN journal_entry_lines.credit - journal_entry_lines.debit ELSE 0 END), 0) as income_net"
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN accounts.account_type = 'expense' THEN journal_entry_lines.debit - journal_entry_lines.credit ELSE 0 END), 0) as expense_net"
            )
            ->first();

        return $this->amountToCents((string) $totals->income_net)
            - $this->amountToCents((string) $totals->expense_net);
    }

    private function applyDefaultCutoffDate(array &$filters, Collection $periods): void
    {
        if (isset($filters['cutoff_date']) || isset($filters['accounting_period_id'])) {
            return;
        }

        $openPeriod = $periods->firstWhere('status', AccountingPeriodStatus::OPEN);

        if ($openPeriod) {
            $filters['accounting_period_id'] = $openPeriod->id;
            $filters['cutoff_date'] = $openPeriod->end_date->format('Y-m-d');
        } else {
            $filters['cutoff_date'] = now()->toDateString();
        }
    }

    private function applyPeriodCutoffDate(array &$filters, Collection $periods): void
    {
        if (! isset($filters['accounting_period_id'])) {
            return;
        }

        $period = $periods->firstWhere('id', (int) $filters['accounting_period_id']);
        $filters['cutoff_date'] = $period->end_date->format('Y-m-d');
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

        if (! $filters['show_zero_balances']) {
            $balanceIds = $accounts
                ->filter(fn (Account $account) => $this->accountBalanceCents($account) !== 0)
                ->pluck('id')
                ->flip();
            $balanceIds = $this->includeAncestors($accounts, $balanceIds);
            $selectedIds = $selectedIds->intersectByKeys($balanceIds);
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
        $directBalanceCents = $this->accountBalanceCents($account);
        $subtotalCents = $directBalanceCents + $children->sum('subtotal_cents');

        return [
            'account' => $account,
            'children' => $children->all(),
            'balance' => $this->centsToAmount(abs($directBalanceCents)),
            'is_contrary' => $directBalanceCents < 0,
            'subtotal' => $this->centsToAmount(abs($subtotalCents)),
            'subtotal_is_contrary' => $subtotalCents < 0,
            'subtotal_cents' => $subtotalCents,
        ];
    }

    private function sectionNetCents(Collection $accounts, AccountType $type): int
    {
        return $accounts->sum(fn (Account $account) => $this->accountBalanceCents($account));
    }

    private function accountBalanceCents(Account $account): int
    {
        $debit = $this->amountToCents((string) $account->accumulated_debit);
        $credit = $this->amountToCents((string) $account->accumulated_credit);

        return $account->account_type === AccountType::ASSET
            ? $debit - $credit
            : $credit - $debit;
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
