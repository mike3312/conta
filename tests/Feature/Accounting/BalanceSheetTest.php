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

class BalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Company $company;

    private AccountingPeriod $period;

    private Account $assetAccount;

    private Account $liabilityAccount;

    private Account $equityAccount;

    private Account $incomeAccount;

    private Account $expenseAccount;

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
        $this->assetAccount = $this->createAccount($this->company, '1.1.01', 'Caja', 'asset', 'debit');
        $this->liabilityAccount = $this->createAccount($this->company, '2.1.01', 'Proveedores', 'liability', 'credit');
        $this->equityAccount = $this->createAccount($this->company, '3.1.01', 'Capital', 'equity', 'credit');
        $this->incomeAccount = $this->createAccount($this->company, '4.1.01', 'Ventas', 'income', 'credit');
        $this->expenseAccount = $this->createAccount($this->company, '5.1.01', 'Alquileres', 'expense', 'debit');

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    public function test_user_can_view_balance_sheet_for_active_company(): void
    {
        $this->recordInitialCapital('100.00');

        $this->get(route('accounting.balance-sheet.index'))
            ->assertOk()
            ->assertSee('Balance General')
            ->assertSee('Caja')
            ->assertSee('Capital');
    }

    public function test_accounts_and_movements_from_other_company_are_not_shown(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos');
        $otherPeriod = $this->createPeriod($otherCompany, 'Enero 2026', '2026-01-01', '2026-01-31');
        $otherAsset = $this->createAccount($otherCompany, '1.1.01', 'Caja ajena', 'asset', 'debit');
        $otherEquity = $this->createAccount($otherCompany, '3.1.01', 'Capital ajeno', 'equity', 'credit');

        $this->recordInitialCapital('100.00');
        $this->createEntry(JournalEntryStatus::POSTED, '2026-01-15', [
            $this->line($otherAsset, '500.00', '0.00'),
            $this->line($otherEquity, '0.00', '500.00'),
        ], company: $otherCompany, period: $otherPeriod);

        $response = $this->get(route('accounting.balance-sheet.index'));

        $response->assertSee('Caja')->assertDontSee('Caja ajena')->assertDontSee('Capital ajeno');
        $this->assertSame('100.00', $response->viewData('totalAssets'));
    }

    public function test_only_posted_entries_are_considered(): void
    {
        $this->recordInitialCapital('100.00', JournalEntryStatus::POSTED);
        $this->recordInitialCapital('500.00', JournalEntryStatus::DRAFT);
        $this->recordInitialCapital('700.00', JournalEntryStatus::VOIDED);

        $response = $this->get(route('accounting.balance-sheet.index'));

        $this->assertSame('100.00', $response->viewData('totalAssets'));
        $this->assertSame('100.00', $response->viewData('baseEquity'));
    }

    public function test_movements_after_cutoff_date_are_excluded(): void
    {
        $this->recordInitialCapital('100.00', date: '2026-01-15');
        $this->recordInitialCapital('200.00', date: '2026-02-15');

        $response = $this->get(route('accounting.balance-sheet.index', [
            'cutoff_date' => '2026-01-31',
        ]));

        $this->assertSame('100.00', $response->viewData('totalAssets'));
    }

    public function test_assets_are_debit_minus_credit(): void
    {
        $this->recordInitialCapital('100.00');
        $this->recordExpense('20.00');

        $response = $this->get(route('accounting.balance-sheet.index'));

        $this->assertSame('80.00', $response->viewData('totalAssets'));
    }

    public function test_liabilities_and_equity_are_credit_minus_debit(): void
    {
        $this->recordInitialCapital('100.00');
        $this->createEntry(JournalEntryStatus::POSTED, '2026-01-16', [
            $this->line($this->assetAccount, '50.00', '0.00'),
            $this->line($this->liabilityAccount, '0.00', '50.00'),
        ]);

        $response = $this->get(route('accounting.balance-sheet.index'));

        $this->assertSame('150.00', $response->viewData('totalAssets'));
        $this->assertSame('50.00', $response->viewData('totalLiabilities'));
        $this->assertSame('100.00', $response->viewData('baseEquity'));
    }

    public function test_income_and_expense_accounts_are_not_displayed_directly(): void
    {
        $this->recordIncome('100.00');
        $this->recordExpense('30.00');

        $this->get(route('accounting.balance-sheet.index'))
            ->assertDontSee('Ventas')
            ->assertDontSee('Alquileres')
            ->assertSee('Resultado acumulado pendiente de cierre');
    }

    public function test_pending_result_is_calculated_correctly(): void
    {
        $this->recordIncome('150.00');
        $this->recordExpense('40.00');

        $response = $this->get(route('accounting.balance-sheet.index'));

        $this->assertSame('110.00', $response->viewData('pendingResult'));
        $this->assertSame('profit', $response->viewData('pendingResultType'));
    }

    public function test_profit_increases_equity_and_loss_decreases_it(): void
    {
        $this->recordInitialCapital('100.00');
        $this->recordIncome('50.00');

        $profitResponse = $this->get(route('accounting.balance-sheet.index'));
        $this->assertSame('150.00', $profitResponse->viewData('totalEquity'));

        $this->recordExpense('80.00');
        $lossResponse = $this->get(route('accounting.balance-sheet.index'));
        $this->assertSame('70.00', $lossResponse->viewData('totalEquity'));
        $this->assertSame('loss', $lossResponse->viewData('pendingResultType'));
        $this->assertSame('30.00', $lossResponse->viewData('pendingResult'));
    }

    public function test_recognizable_closing_entry_does_not_duplicate_result(): void
    {
        $this->recordIncome('100.00');
        $this->createEntry(JournalEntryStatus::POSTED, '2026-01-31', [
            $this->line($this->incomeAccount, '100.00', '0.00'),
            $this->line($this->equityAccount, '0.00', '100.00'),
        ]);

        $response = $this->get(route('accounting.balance-sheet.index'));

        $this->assertSame('0.00', $response->viewData('pendingResult'));
        $this->assertSame('100.00', $response->viewData('baseEquity'));
        $this->assertSame('100.00', $response->viewData('totalEquity'));
        $this->assertTrue($response->viewData('isBalanced'));
    }

    public function test_parent_and_child_accounts_do_not_duplicate_amounts(): void
    {
        $parent = $this->createAccount($this->company, '1.1', 'Activo corriente', 'asset', 'debit', false);
        $this->assetAccount->update(['parent_id' => $parent->id]);
        $this->recordInitialCapital('100.00');

        $response = $this->get(route('accounting.balance-sheet.index'));
        $node = $response->viewData('assetSection')[0];

        $this->assertSame($parent->id, $node['account']->id);
        $this->assertCount(1, $node['children']);
        $this->assertSame('100.00', $node['subtotal']);
        $this->assertSame('100.00', $response->viewData('totalAssets'));
    }

    public function test_balanced_equation_shows_corresponding_badge(): void
    {
        $this->recordInitialCapital('100.00');

        $response = $this->get(route('accounting.balance-sheet.index'));

        $this->assertTrue($response->viewData('isBalanced'));
        $this->assertSame('0.00', $response->viewData('difference'));
        $response->assertSee('Balance cuadrado');
    }

    public function test_unbalanced_equation_shows_exact_difference(): void
    {
        $entry = $this->createEntry(JournalEntryStatus::POSTED, '2026-01-15', [
            $this->line($this->assetAccount, '25.00', '0.00'),
        ]);

        $response = $this->get(route('accounting.balance-sheet.index'));

        $this->assertFalse($response->viewData('isBalanced'));
        $this->assertSame('25.00', $response->viewData('difference'));
        $response->assertSee('Balance descuadrado');
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
    }

    public function test_cutoff_date_and_period_filters_work(): void
    {
        $february = $this->createPeriod($this->company, 'Febrero 2026', '2026-02-01', '2026-02-28');
        $this->recordInitialCapital('100.00', date: '2026-01-15');
        $this->recordInitialCapital('200.00', date: '2026-02-15', period: $february);

        $response = $this->get(route('accounting.balance-sheet.index', [
            'accounting_period_id' => $february->id,
        ]));

        $this->assertSame('300.00', $response->viewData('totalAssets'));
        $this->assertSame('2026-02-28', $response->viewData('filters')['cutoff_date']);
    }

    public function test_period_from_other_company_is_rejected(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos');
        $otherPeriod = $this->createPeriod($otherCompany, 'Enero 2026', '2026-01-01', '2026-01-31');

        $this->get(route('accounting.balance-sheet.index', [
            'accounting_period_id' => $otherPeriod->id,
        ]))->assertSessionHasErrors('accounting_period_id');
    }

    public function test_zero_balance_accounts_are_hidden_by_default_and_can_be_shown(): void
    {
        $emptyAsset = $this->createAccount($this->company, '1.9.99', 'Activo sin saldo', 'asset', 'debit');

        $default = $this->get(route('accounting.balance-sheet.index'));
        $this->assertFalse($this->sectionContainsAccount($default->viewData('assetSection'), $emptyAsset));

        $withZero = $this->get(route('accounting.balance-sheet.index', ['show_zero_balances' => 1]));
        $this->assertTrue($this->sectionContainsAccount($withZero->viewData('assetSection'), $emptyAsset));
    }

    public function test_empty_view_works_correctly(): void
    {
        $this->get(route('accounting.balance-sheet.index'))
            ->assertOk()
            ->assertSee('No existen movimientos contables hasta la fecha de corte seleccionada.');
    }

    public function test_print_version_hides_navigation_filters_and_buttons(): void
    {
        $this->get(route('accounting.balance-sheet.index'))
            ->assertOk()
            ->assertSee('@media print', false)
            ->assertSee('body > .d-flex > .bg-dark', false)
            ->assertSee('balance-sheet-no-print', false);
    }

    public function test_summary_cards_use_same_report_totals(): void
    {
        $this->recordInitialCapital('100.00');
        $this->recordIncome('50.00');

        $response = $this->get(route('accounting.balance-sheet.index'));
        $content = $response->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($content, 'GTQ 150.00'));
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'GTQ 50.00'));
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'GTQ 0.00'));
    }

    public function test_company_without_access_in_session_is_replaced(): void
    {
        $otherCompany = $this->createCompany('Empresa sin acceso');

        $this->withSession(['company_id' => $otherCompany->id])
            ->get(route('accounting.balance-sheet.index'))
            ->assertOk()
            ->assertSessionHas('company_id', $this->company->id);
    }

    private function recordInitialCapital(
        string $amount,
        JournalEntryStatus $status = JournalEntryStatus::POSTED,
        string $date = '2026-01-15',
        ?AccountingPeriod $period = null
    ): JournalEntry {
        return $this->createEntry($status, $date, [
            $this->line($this->assetAccount, $amount, '0.00'),
            $this->line($this->equityAccount, '0.00', $amount),
        ], period: $period);
    }

    private function recordIncome(string $amount): JournalEntry
    {
        return $this->createEntry(JournalEntryStatus::POSTED, '2026-01-15', [
            $this->line($this->assetAccount, $amount, '0.00'),
            $this->line($this->incomeAccount, '0.00', $amount),
        ]);
    }

    private function recordExpense(string $amount): JournalEntry
    {
        return $this->createEntry(JournalEntryStatus::POSTED, '2026-01-16', [
            $this->line($this->expenseAccount, $amount, '0.00'),
            $this->line($this->assetAccount, '0.00', $amount),
        ]);
    }

    private function createEntry(
        JournalEntryStatus $status,
        string $date,
        array $lines,
        ?Company $company = null,
        ?AccountingPeriod $period = null
    ): JournalEntry {
        $company ??= $this->company;
        $period ??= $this->period;
        $number = JournalEntry::where('company_id', $company->id)
            ->where('accounting_period_id', $period->id)
            ->max('number');
        $entry = JournalEntry::create([
            'company_id' => $company->id,
            'accounting_period_id' => $period->id,
            'number' => $status === JournalEntryStatus::DRAFT ? null : ((int) $number + 1),
            'entry_date' => $date,
            'description' => 'Movimiento de prueba',
            'status' => $status->value,
            'created_by' => $this->user->id,
            'posted_by' => $status === JournalEntryStatus::POSTED ? $this->user->id : null,
            'posted_at' => $status === JournalEntryStatus::POSTED ? now() : null,
        ]);

        foreach ($lines as $index => $line) {
            $entry->lines()->create($line + ['line_order' => $index + 1]);
        }

        return $entry;
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

    private function line(Account $account, string $debit, string $credit): array
    {
        return ['account_id' => $account->id, 'debit' => $debit, 'credit' => $credit];
    }

    private function sectionContainsAccount(array $nodes, Account $account): bool
    {
        foreach ($nodes as $node) {
            if ($node['account']->is($account) || $this->sectionContainsAccount($node['children'], $account)) {
                return true;
            }
        }

        return false;
    }
}
