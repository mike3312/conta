<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrialBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Company $company;

    private AccountingPeriod $period;

    private Account $debitAccount;

    private Account $creditAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Tenant de pruebas',
            'email' => 'tenant@example.com',
            'status' => 'ACTIVE',
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->company = $this->createCompany('Empresa Uno');
        $this->company->users()->attach($this->user->id, [
            'is_owner' => true,
            'is_active' => true,
            'joined_at' => now(),
        ]);
        $this->period = $this->createPeriod($this->company, 'Enero 2026', '2026-01-01', '2026-01-31');
        $this->debitAccount = $this->createAccount($this->company, '1.1.01', 'Caja', 'asset', 'debit');
        $this->creditAccount = $this->createAccount($this->company, '4.1.01', 'Ingresos', 'income', 'credit');

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    public function test_user_can_view_trial_balance_for_active_company(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-15');

        $this->get(route('accounting.trial-balance.index'))
            ->assertOk()
            ->assertSee('Balance de Comprobación')
            ->assertSee('Caja');
    }

    public function test_accounts_and_movements_from_other_company_are_not_shown(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos');
        $otherPeriod = $this->createPeriod($otherCompany, 'Enero 2026', '2026-01-01', '2026-01-31');
        $otherDebit = $this->createAccount($otherCompany, '1.1.01', 'Caja ajena', 'asset', 'debit');
        $otherCredit = $this->createAccount($otherCompany, '4.1.01', 'Ingreso ajeno', 'income', 'credit');

        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-15');
        $this->createEntry(
            JournalEntryStatus::POSTED,
            1,
            '2026-01-15',
            $otherDebit,
            $otherCredit,
            company: $otherCompany,
            period: $otherPeriod
        );

        $rows = $this->get(route('accounting.trial-balance.index'))->viewData('trialBalance');

        $this->assertTrue(collect($rows->items())->contains(fn ($row) => $row['account']->is($this->debitAccount)));
        $this->assertFalse(collect($rows->items())->contains(fn ($row) => $row['account']->is($otherDebit)));
    }

    public function test_only_posted_entries_are_considered(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10', amount: '100.00');
        $this->createEntry(JournalEntryStatus::DRAFT, null, '2026-01-11', amount: '500.00');
        $this->createEntry(JournalEntryStatus::VOIDED, 2, '2026-01-12', amount: '700.00');

        $row = $this->rowFor($this->get(route('accounting.trial-balance.index')), $this->debitAccount);

        $this->assertSame('100.00', $row['range_debit']);
        $this->assertSame('100.00', $row['final_debit']);
    }

    public function test_previous_balance_is_calculated_correctly(): void
    {
        $december = $this->createPeriod($this->company, 'Diciembre 2025', '2025-12-01', '2025-12-31');
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2025-12-20', amount: '40.50', period: $december);
        $this->createEntry(JournalEntryStatus::POSTED, 2, '2026-01-10', amount: '100.00');

        $row = $this->rowFor(
            $this->get(route('accounting.trial-balance.index', [
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
            ])),
            $this->debitAccount
        );

        $this->assertSame('40.50', $row['previous_debit']);
        $this->assertSame('0.00', $row['previous_credit']);
    }

    public function test_range_movements_and_final_balance_are_calculated_correctly(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10', amount: '100.25');
        $this->createEntry(
            JournalEntryStatus::POSTED,
            2,
            '2026-01-15',
            $this->creditAccount,
            $this->debitAccount,
            '30.25'
        );

        $row = $this->rowFor($this->get(route('accounting.trial-balance.index')), $this->debitAccount);

        $this->assertSame('100.25', $row['range_debit']);
        $this->assertSame('30.25', $row['range_credit']);
        $this->assertSame('70.00', $row['final_debit']);
        $this->assertSame('0.00', $row['final_credit']);
    }

    public function test_positive_and_negative_balances_use_separate_columns(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10', amount: '75.00');

        $response = $this->get(route('accounting.trial-balance.index'));
        $debitRow = $this->rowFor($response, $this->debitAccount);
        $creditRow = $this->rowFor($response, $this->creditAccount);

        $this->assertSame('75.00', $debitRow['final_debit']);
        $this->assertSame('0.00', $debitRow['final_credit']);
        $this->assertSame('0.00', $creditRow['final_debit']);
        $this->assertSame('75.00', $creditRow['final_credit']);
    }

    public function test_general_movement_and_final_totals_are_balanced(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10', amount: '100.10');
        $this->createEntry(JournalEntryStatus::POSTED, 2, '2026-01-15', amount: '50.20');

        $response = $this->get(route('accounting.trial-balance.index'));
        $totals = $response->viewData('generalTotals');

        $this->assertSame($this->toCents($totals->range_debit), $this->toCents($totals->range_credit));
        $this->assertSame($this->toCents($totals->final_debit), $this->toCents($totals->final_credit));
        $this->assertTrue($response->viewData('balanceStatus')['is_balanced']);
    }

    public function test_date_period_type_and_account_range_filters_work(): void
    {
        $february = $this->createPeriod($this->company, 'Febrero 2026', '2026-02-01', '2026-02-28');
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10');
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-02-10', period: $february);

        $response = $this->get(route('accounting.trial-balance.index', [
            'accounting_period_id' => $february->id,
            'account_type' => 'asset',
            'account_from_id' => $this->debitAccount->id,
            'account_to_id' => $this->debitAccount->id,
        ]));
        $rows = $response->viewData('trialBalance');

        $this->assertCount(1, $rows->items());
        $this->assertSame('100.00', $rows->items()[0]['range_debit']);
        $this->assertSame('2026-02-01', $response->viewData('filters')['date_from']);
        $this->assertSame('2026-02-28', $response->viewData('filters')['date_to']);
    }

    public function test_accounts_and_periods_from_other_company_are_rejected(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos');
        $otherPeriod = $this->createPeriod($otherCompany, 'Enero 2026', '2026-01-01', '2026-01-31');
        $otherAccount = $this->createAccount($otherCompany, '1.1.01', 'Cuenta ajena', 'asset', 'debit');

        $this->get(route('accounting.trial-balance.index', [
            'accounting_period_id' => $otherPeriod->id,
            'account_from_id' => $otherAccount->id,
        ]))->assertSessionHasErrors(['accounting_period_id', 'account_from_id']);
    }

    public function test_parent_accounts_do_not_duplicate_amounts(): void
    {
        $parent = $this->createAccount($this->company, '1.1', 'Activo corriente', 'asset', 'debit', false);
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10', amount: '100.00');

        $response = $this->get(route('accounting.trial-balance.index'));
        $rows = collect($response->viewData('trialBalance')->items());
        $totals = $response->viewData('generalTotals');

        $this->assertFalse($rows->contains(fn ($row) => $row['account']->is($parent)));
        $this->assertSame(10000, $this->toCents($totals->range_debit));
        $this->assertSame(10000, $this->toCents($totals->range_credit));
    }

    public function test_totals_do_not_change_between_pages(): void
    {
        $commonCredit = $this->createAccount($this->company, '0.1', 'Contrapartida común', 'liability', 'credit');

        foreach (range(1, 26) as $number) {
            $debit = $this->createAccount(
                $this->company,
                '1.2.'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                "Cuenta $number",
                'asset',
                'debit'
            );
            $this->createEntry(JournalEntryStatus::POSTED, $number, '2026-01-15', $debit, $commonCredit, '10.00');
        }

        $firstPage = $this->get(route('accounting.trial-balance.index'));
        $secondPage = $this->get(route('accounting.trial-balance.index', ['page' => 2]));

        $this->assertCount(25, $firstPage->viewData('trialBalance')->items());
        $this->assertSame(
            $this->toCents($firstPage->viewData('generalTotals')->range_debit),
            $this->toCents($secondPage->viewData('generalTotals')->range_debit)
        );
        $this->assertSame(
            $this->toCents($firstPage->viewData('generalTotals')->final_credit),
            $this->toCents($secondPage->viewData('generalTotals')->final_credit)
        );
    }

    public function test_accounts_without_movements_and_zero_balances_follow_visibility_filters(): void
    {
        $emptyAccount = $this->createAccount($this->company, '1.9.99', 'Cuenta vacía', 'asset', 'debit');

        $defaultResponse = $this->get(route('accounting.trial-balance.index'));
        $this->assertFalse($this->containsAccount($defaultResponse, $emptyAccount));

        $withEmpty = $this->get(route('accounting.trial-balance.index', ['show_without_movements' => 1]));
        $this->assertTrue($this->containsAccount($withEmpty, $emptyAccount));

        $onlyBalances = $this->get(route('accounting.trial-balance.index', [
            'show_without_movements' => 1,
            'only_with_balance' => 1,
        ]));
        $this->assertFalse($this->containsAccount($onlyBalances, $emptyAccount));
    }

    public function test_empty_report_works_correctly(): void
    {
        $this->get(route('accounting.trial-balance.index'))
            ->assertOk()
            ->assertSee('No existen cuentas con información para los filtros seleccionados.');
    }

    public function test_accounting_difference_generates_visible_alert(): void
    {
        $entry = JournalEntry::create([
            'company_id' => $this->company->id,
            'accounting_period_id' => $this->period->id,
            'number' => 1,
            'entry_date' => '2026-01-15',
            'description' => 'Póliza descuadrada',
            'status' => JournalEntryStatus::POSTED->value,
            'created_by' => $this->user->id,
            'posted_by' => $this->user->id,
            'posted_at' => now(),
        ]);
        $entry->lines()->create([
            'account_id' => $this->debitAccount->id,
            'debit' => '10.00',
            'credit' => '0.00',
            'line_order' => 1,
        ]);

        $response = $this->get(route('accounting.trial-balance.index'));

        $response->assertOk()->assertSee('Descuadrado');
        $this->assertFalse($response->viewData('balanceStatus')['is_balanced']);
        $this->assertSame('10.00', $response->viewData('balanceStatus')['movement_difference']);
    }

    public function test_company_without_access_in_session_is_replaced(): void
    {
        $otherCompany = $this->createCompany('Empresa sin acceso');

        $this->withSession(['company_id' => $otherCompany->id])
            ->get(route('accounting.trial-balance.index'))
            ->assertOk()
            ->assertSessionHas('company_id', $this->company->id);
    }

    private function createCompany(string $name): Company
    {
        return Company::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'legal_name' => $name.' Sociedad Anónima',
            'tax_id' => fake()->unique()->numerify('#########'),
            'status' => 'ACTIVE',
        ]);
    }

    private function createPeriod(Company $company, string $name, string $start, string $end): AccountingPeriod
    {
        return AccountingPeriod::create([
            'company_id' => $company->id,
            'name' => $name,
            'start_date' => $start,
            'end_date' => $end,
            'status' => AccountingPeriodStatus::OPEN->value,
        ]);
    }

    private function createAccount(
        Company $company,
        string $code,
        string $name,
        string $type,
        string $nature,
        bool $allowsEntries = true
    ): Account {
        return Account::create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'nature' => $nature,
            'allows_entries' => $allowsEntries,
            'level' => 1,
            'is_active' => true,
        ]);
    }

    private function createEntry(
        JournalEntryStatus $status,
        ?int $number,
        string $date,
        ?Account $debitAccount = null,
        ?Account $creditAccount = null,
        string $amount = '100.00',
        ?Company $company = null,
        ?AccountingPeriod $period = null
    ): JournalEntry {
        $debitAccount ??= $this->debitAccount;
        $creditAccount ??= $this->creditAccount;
        $company ??= $this->company;
        $period ??= $this->period;

        $entry = JournalEntry::create([
            'company_id' => $company->id,
            'accounting_period_id' => $period->id,
            'number' => $number,
            'entry_date' => $date,
            'description' => 'Movimiento de prueba',
            'status' => $status->value,
            'created_by' => $this->user->id,
            'posted_by' => $status === JournalEntryStatus::POSTED ? $this->user->id : null,
            'posted_at' => $status === JournalEntryStatus::POSTED ? now() : null,
        ]);
        $entry->lines()->create([
            'account_id' => $debitAccount->id,
            'debit' => $amount,
            'credit' => '0.00',
            'line_order' => 1,
        ]);
        $entry->lines()->create([
            'account_id' => $creditAccount->id,
            'debit' => '0.00',
            'credit' => $amount,
            'line_order' => 2,
        ]);

        return $entry;
    }

    private function rowFor($response, Account $account): array
    {
        return collect($response->viewData('trialBalance')->items())
            ->first(fn ($row) => $row['account']->is($account));
    }

    private function containsAccount($response, Account $account): bool
    {
        return collect($response->viewData('trialBalance')->items())
            ->contains(fn ($row) => $row['account']->is($account));
    }

    private function toCents(string $amount): int
    {
        [$whole, $decimals] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($decimals, 0, 2), 2, '0');
    }
}
