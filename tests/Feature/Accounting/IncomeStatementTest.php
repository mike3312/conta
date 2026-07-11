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

class IncomeStatementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Company $company;

    private AccountingPeriod $period;

    private Account $assetAccount;

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
        $this->incomeAccount = $this->createAccount($this->company, '4.1.01', 'Ventas', 'income', 'credit');
        $this->expenseAccount = $this->createAccount($this->company, '5.1.01', 'Alquileres', 'expense', 'debit');

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    public function test_authenticated_user_can_view_income_statement_for_active_company(): void
    {
        $this->recordIncome('100.00');

        $this->get(route('accounting.income-statement.index'))
            ->assertOk()
            ->assertSee('Estado de Resultados')
            ->assertSee($this->company->name)
            ->assertSee('Ventas');
    }

    public function test_accounts_and_movements_from_other_company_are_not_shown(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos');
        $otherPeriod = $this->createPeriod($otherCompany, 'Enero 2026', '2026-01-01', '2026-01-31');
        $otherAsset = $this->createAccount($otherCompany, '1.1.01', 'Caja ajena', 'asset', 'debit');
        $otherIncome = $this->createAccount($otherCompany, '4.1.01', 'Ingreso ajeno', 'income', 'credit');

        $this->recordIncome('100.00');
        $this->createEntry(JournalEntryStatus::POSTED, '2026-01-15', [
            $this->line($otherAsset, '100.00', '0.00'),
            $this->line($otherIncome, '0.00', '100.00'),
        ], company: $otherCompany, period: $otherPeriod);

        $response = $this->get(route('accounting.income-statement.index'));

        $response->assertSee('Ventas')->assertDontSee('Ingreso ajeno');
        $this->assertSame('100.00', $response->viewData('totalIncome'));
    }

    public function test_only_posted_entries_are_considered(): void
    {
        $this->recordIncome('100.00', JournalEntryStatus::POSTED);
        $this->recordIncome('500.00', JournalEntryStatus::DRAFT);
        $this->recordIncome('700.00', JournalEntryStatus::VOIDED);

        $response = $this->get(route('accounting.income-statement.index'));

        $this->assertSame('100.00', $response->viewData('totalIncome'));
    }

    public function test_only_income_and_expense_accounts_are_included(): void
    {
        $liability = $this->createAccount($this->company, '2.1.01', 'Proveedores', 'liability', 'credit');
        $equity = $this->createAccount($this->company, '3.1.01', 'Capital', 'equity', 'credit');
        $cost = $this->createAccount($this->company, '6.1.01', 'Costo de ventas', 'cost', 'debit');

        $this->recordIncome('100.00');
        $this->createEntry(JournalEntryStatus::POSTED, '2026-01-16', [
            $this->line($cost, '30.00', '0.00'),
            $this->line($liability, '0.00', '30.00'),
        ]);
        $this->createEntry(JournalEntryStatus::POSTED, '2026-01-17', [
            $this->line($this->assetAccount, '20.00', '0.00'),
            $this->line($equity, '0.00', '20.00'),
        ]);

        $response = $this->get(route('accounting.income-statement.index'));

        $response->assertSee('Ventas')
            ->assertDontSee('Proveedores')
            ->assertDontSee('Capital')
            ->assertDontSee('Costo de ventas');
        $this->assertSame('100.00', $response->viewData('totalIncome'));
        $this->assertSame('0.00', $response->viewData('totalExpense'));
    }

    public function test_income_is_credit_minus_debit(): void
    {
        $this->recordIncome('100.00');
        $this->createEntry(JournalEntryStatus::POSTED, '2026-01-16', [
            $this->line($this->incomeAccount, '20.00', '0.00'),
            $this->line($this->assetAccount, '0.00', '20.00'),
        ]);

        $response = $this->get(route('accounting.income-statement.index'));

        $this->assertSame('80.00', $response->viewData('totalIncome'));
        $this->assertFalse($response->viewData('incomeIsContrary'));
    }

    public function test_expense_is_debit_minus_credit(): void
    {
        $this->recordExpense('50.00');
        $this->createEntry(JournalEntryStatus::POSTED, '2026-01-16', [
            $this->line($this->assetAccount, '10.00', '0.00'),
            $this->line($this->expenseAccount, '0.00', '10.00'),
        ]);

        $response = $this->get(route('accounting.income-statement.index'));

        $this->assertSame('40.00', $response->viewData('totalExpense'));
        $this->assertFalse($response->viewData('expenseIsContrary'));
    }

    public function test_profit_is_calculated_correctly(): void
    {
        $this->recordIncome('150.00');
        $this->recordExpense('40.00');

        $response = $this->get(route('accounting.income-statement.index'));

        $this->assertSame('110.00', $response->viewData('result'));
        $this->assertSame('profit', $response->viewData('resultType'));
        $response->assertSee('Utilidad del período');
    }

    public function test_loss_is_calculated_and_presented_without_negative_value(): void
    {
        $this->recordIncome('50.00');
        $this->recordExpense('80.00');

        $response = $this->get(route('accounting.income-statement.index'));

        $this->assertSame('30.00', $response->viewData('result'));
        $this->assertSame('loss', $response->viewData('resultType'));
        $response->assertSee('Pérdida del período')->assertDontSee('-30.00');
    }

    public function test_zero_result_is_presented_correctly(): void
    {
        $this->recordIncome('50.00');
        $this->recordExpense('50.00');

        $response = $this->get(route('accounting.income-statement.index'));

        $this->assertSame('0.00', $response->viewData('result'));
        $this->assertSame('zero', $response->viewData('resultType'));
        $response->assertSee('Resultado del período');
    }

    public function test_date_and_period_filters_work_without_previous_balances(): void
    {
        $december = $this->createPeriod($this->company, 'Diciembre 2025', '2025-12-01', '2025-12-31');
        $february = $this->createPeriod($this->company, 'Febrero 2026', '2026-02-01', '2026-02-28');

        $this->recordIncome('500.00', date: '2025-12-20', period: $december);
        $this->recordIncome('100.00', date: '2026-01-15');
        $this->recordIncome('200.00', date: '2026-02-15', period: $february);

        $response = $this->get(route('accounting.income-statement.index', [
            'accounting_period_id' => $february->id,
        ]));

        $this->assertSame('200.00', $response->viewData('totalIncome'));
        $this->assertSame('2026-02-01', $response->viewData('filters')['date_from']);
        $this->assertSame('2026-02-28', $response->viewData('filters')['date_to']);
    }

    public function test_parent_and_child_accounts_do_not_duplicate_amounts(): void
    {
        $parent = $this->createAccount($this->company, '4.1', 'Ingresos ordinarios', 'income', 'credit', false);
        $this->incomeAccount->update(['parent_id' => $parent->id]);
        $this->recordIncome('100.00');

        $response = $this->get(route('accounting.income-statement.index'));
        $node = $response->viewData('incomeSection')[0];

        $this->assertSame($parent->id, $node['account']->id);
        $this->assertCount(1, $node['children']);
        $this->assertSame('100.00', $node['subtotal']);
        $this->assertSame('100.00', $response->viewData('totalIncome'));
    }

    public function test_period_from_other_company_is_rejected(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos');
        $otherPeriod = $this->createPeriod($otherCompany, 'Enero 2026', '2026-01-01', '2026-01-31');

        $this->get(route('accounting.income-statement.index', [
            'accounting_period_id' => $otherPeriod->id,
        ]))->assertSessionHasErrors('accounting_period_id');
    }

    public function test_accounts_without_movements_are_hidden_by_default_and_can_be_shown(): void
    {
        $emptyIncome = $this->createAccount($this->company, '4.9.99', 'Ingreso sin movimientos', 'income', 'credit');

        $default = $this->get(route('accounting.income-statement.index'));
        $this->assertFalse($this->sectionContainsAccount($default->viewData('incomeSection'), $emptyIncome));

        $withEmpty = $this->get(route('accounting.income-statement.index', ['show_without_movements' => 1]));
        $this->assertTrue($this->sectionContainsAccount($withEmpty->viewData('incomeSection'), $emptyIncome));
    }

    public function test_contrary_balance_is_identified(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, '2026-01-15', [
            $this->line($this->incomeAccount, '25.00', '0.00'),
            $this->line($this->assetAccount, '0.00', '25.00'),
        ]);

        $response = $this->get(route('accounting.income-statement.index'));

        $this->assertTrue($response->viewData('incomeIsContrary'));
        $response->assertSee('Saldo contrario');
    }

    public function test_empty_report_works_correctly(): void
    {
        $this->get(route('accounting.income-statement.index'))
            ->assertOk()
            ->assertSee('No existen movimientos de ingresos o gastos para los filtros seleccionados.');
    }

    public function test_print_version_hides_navigation_filters_and_buttons(): void
    {
        $response = $this->get(route('accounting.income-statement.index'));

        $response->assertOk()
            ->assertSee('@media print', false)
            ->assertSee('body > .d-flex > .bg-dark', false)
            ->assertSee('income-statement-no-print', false);
    }

    public function test_cards_and_report_use_the_same_totals(): void
    {
        $this->recordIncome('120.00');
        $this->recordExpense('45.00');

        $response = $this->get(route('accounting.income-statement.index'));
        $content = $response->getContent();

        $this->assertGreaterThanOrEqual(2, substr_count($content, 'GTQ 120.00'));
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'GTQ 45.00'));
        $this->assertGreaterThanOrEqual(2, substr_count($content, 'GTQ 75.00'));
    }

    public function test_user_without_access_to_active_company_is_forbidden(): void
    {
        $otherCompany = $this->createCompany('Empresa sin acceso');

        $this->withSession(['company_id' => $otherCompany->id])
            ->get(route('accounting.income-statement.index'))
            ->assertForbidden();
    }

    private function recordIncome(
        string $amount,
        JournalEntryStatus $status = JournalEntryStatus::POSTED,
        string $date = '2026-01-15',
        ?AccountingPeriod $period = null
    ): JournalEntry {
        return $this->createEntry($status, $date, [
            $this->line($this->assetAccount, $amount, '0.00'),
            $this->line($this->incomeAccount, '0.00', $amount),
        ], period: $period);
    }

    private function recordExpense(string $amount): JournalEntry
    {
        return $this->createEntry(JournalEntryStatus::POSTED, '2026-01-15', [
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
        return [
            'account_id' => $account->id,
            'debit' => $debit,
            'credit' => $credit,
        ];
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
