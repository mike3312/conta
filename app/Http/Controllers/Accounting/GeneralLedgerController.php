<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountNature;
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
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GeneralLedgerController extends Controller
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
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'search' => ['nullable', 'string', 'max:255'],
            'show_without_movements' => ['nullable', 'boolean'],
        ], [
            'date_from.date' => 'La fecha inicial no es válida.',
            'date_to.date' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'accounting_period_id.integer' => 'El período contable seleccionado no es válido.',
            'accounting_period_id.exists' => 'El período contable seleccionado no pertenece a la empresa activa.',
            'account_from_id.integer' => 'La cuenta inicial seleccionada no es válida.',
            'account_from_id.exists' => 'La cuenta inicial no pertenece a la empresa activa.',
            'account_to_id.integer' => 'La cuenta final seleccionada no es válida.',
            'account_to_id.exists' => 'La cuenta final no pertenece a la empresa activa.',
            'account_id.integer' => 'La cuenta seleccionada no es válida.',
            'account_id.exists' => 'La cuenta seleccionada no pertenece a la empresa activa.',
            'search.max' => 'El texto de búsqueda no puede exceder 255 caracteres.',
            'show_without_movements.boolean' => 'La opción para mostrar cuentas sin movimientos no es válida.',
        ]);

        $filters['show_without_movements'] = $request->boolean('show_without_movements');

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

        $accountsQuery = Account::where('company_id', $companyId);

        $this->applyAccountFilters($accountsQuery, $filters, $accountOptions);

        if (! $filters['show_without_movements']) {
            $accountsQuery->whereIn('id', $this->movementAccountIdsQuery($companyId, $filters));
        }

        $accountsQuery->orderBy('code');
        $accounts = $request->attributes->getBoolean('exporting')
            ? $accountsQuery->get()
            : $accountsQuery->paginate(10)->withQueryString();

        $accountCollection = $accounts instanceof LengthAwarePaginator
            ? $accounts->getCollection()
            : $accounts;
        $accountIds = $accountCollection->pluck('id');
        $movements = $this->getMovements($companyId, $accountIds, $filters)
            ->groupBy('account_id');
        $previousBalances = $this->getPreviousBalances($companyId, $accountIds, $filters)
            ->keyBy('account_id');

        $preparedAccounts = $accountCollection->map(
            fn (Account $account) => $this->buildAccountLedger(
                $account,
                $movements->get($account->id, collect()),
                $previousBalances->get($account->id)
            )
        );
        if ($accounts instanceof LengthAwarePaginator) {
            $accounts->setCollection($preparedAccounts);
        } else {
            $accounts = $preparedAccounts;
        }

        return view('accounting.general_ledger.index', [
            'ledgerAccounts' => $accounts,
            'periods' => $periods,
            'accountOptions' => $accountOptions,
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

        return $this->pdfExporter->download('accounting.general_ledger.exports.pdf', 'libro-mayor', $company, $data, now($company->timezone), 'landscape');
    }

    public function exportExcel(Request $request)
    {
        $company = $this->activeCompany($request);
        abort_unless($company, 403);
        $request->attributes->set('exporting', true);
        $data = $this->index($request)->getData();

        return $this->excelExporter->generalLedger($company, $data, now($company->timezone));
    }

    private function activeCompany(Request $request): ?Company
    {
        $companyId = (int) session('company_id');

        return $companyId ? $request->user()->companies()->active()->wherePivot('is_active', true)->whereKey($companyId)->first() : null;
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
            ->when($filters['account_id'] ?? null, fn ($query, $accountId) => $query->whereKey($accountId))
            ->when($filters['account_from_id'] ?? null, function ($query, $accountId) use ($accounts) {
                $query->where('code', '>=', $accounts->firstWhere('id', (int) $accountId)->code);
            })
            ->when($filters['account_to_id'] ?? null, function ($query, $accountId) use ($accounts) {
                $query->where('code', '<=', $accounts->firstWhere('id', (int) $accountId)->code);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            });
    }

    private function movementAccountIdsQuery(int $companyId, array $filters)
    {
        $query = JournalEntryLine::query()
            ->select('journal_entry_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', JournalEntryStatus::POSTED->value);

        $this->applyDateRange($query, $filters);

        return $query->distinct();
    }

    private function getMovements(int $companyId, Collection $accountIds, array $filters): Collection
    {
        if ($accountIds->isEmpty()) {
            return collect();
        }

        $query = JournalEntryLine::query()
            ->select([
                'journal_entry_lines.*',
                'journal_entries.id as entry_id',
                'journal_entries.entry_date',
                'journal_entries.number',
                'journal_entries.description as entry_description',
            ])
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entry_lines.account_id', $accountIds)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', JournalEntryStatus::POSTED->value);

        $this->applyDateRange($query, $filters);

        return $query
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.number')
            ->orderBy('journal_entry_lines.line_order')
            ->orderBy('journal_entry_lines.id')
            ->get();
    }

    private function getPreviousBalances(int $companyId, Collection $accountIds, array $filters): Collection
    {
        if ($accountIds->isEmpty() || ! isset($filters['date_from'])) {
            return collect();
        }

        return JournalEntryLine::query()
            ->selectRaw(
                'journal_entry_lines.account_id, COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit, COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'
            )
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entry_lines.account_id', $accountIds)
            ->where('journal_entries.company_id', $companyId)
            ->where('journal_entries.status', JournalEntryStatus::POSTED->value)
            ->whereDate('journal_entries.entry_date', '<', $filters['date_from'])
            ->groupBy('journal_entry_lines.account_id')
            ->get();
    }

    private function applyDateRange(Builder $query, array $filters): void
    {
        $query
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('journal_entries.entry_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('journal_entries.entry_date', '<=', $date));
    }

    private function buildAccountLedger(Account $account, Collection $movements, $previousBalance): array
    {
        $previousDebitCents = $this->amountToCents((string) ($previousBalance->total_debit ?? '0.00'));
        $previousCreditCents = $this->amountToCents((string) ($previousBalance->total_credit ?? '0.00'));
        $previousBalanceCents = $previousDebitCents - $previousCreditCents;
        $runningBalanceCents = $previousBalanceCents;
        $rangeDebitCents = 0;
        $rangeCreditCents = 0;

        $preparedMovements = $movements->map(function ($movement) use (&$runningBalanceCents, &$rangeDebitCents, &$rangeCreditCents) {
            $debitCents = $this->amountToCents((string) $movement->debit);
            $creditCents = $this->amountToCents((string) $movement->credit);
            $rangeDebitCents += $debitCents;
            $rangeCreditCents += $creditCents;
            $runningBalanceCents += $debitCents - $creditCents;

            return [
                'entry_id' => $movement->entry_id,
                'entry_date' => $movement->entry_date,
                'number' => $movement->number,
                'entry_description' => $movement->entry_description,
                'line_description' => $movement->description,
                'debit' => $this->centsToAmount($debitCents),
                'credit' => $this->centsToAmount($creditCents),
                'balance' => $this->centsToAmount(abs($runningBalanceCents)),
                'balance_type' => $this->balanceType($runningBalanceCents),
            ];
        });

        return [
            'account' => $account,
            'movements' => $preparedMovements,
            'previous_balance' => $this->centsToAmount(abs($previousBalanceCents)),
            'previous_balance_type' => $this->balanceType($previousBalanceCents),
            'total_debit' => $this->centsToAmount($rangeDebitCents),
            'total_credit' => $this->centsToAmount($rangeCreditCents),
            'final_balance' => $this->centsToAmount(abs($runningBalanceCents)),
            'final_balance_type' => $this->balanceType($runningBalanceCents),
            'is_normal_balance' => $this->isNormalBalance($account->nature, $runningBalanceCents),
        ];
    }

    private function balanceType(int $balanceCents): string
    {
        return match (true) {
            $balanceCents > 0 => 'Deudor',
            $balanceCents < 0 => 'Acreedor',
            default => 'Sin saldo',
        };
    }

    private function isNormalBalance(AccountNature $nature, int $balanceCents): bool
    {
        return $balanceCents === 0
            || ($nature === AccountNature::DEBIT && $balanceCents > 0)
            || ($nature === AccountNature::CREDIT && $balanceCents < 0);
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
