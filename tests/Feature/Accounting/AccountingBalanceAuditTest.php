<?php

namespace Tests\Feature\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingBalanceAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private AccountingPeriod $period;

    private Account $asset;

    private Account $liability;

    private Account $income;

    private Account $expense;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = $this->createCompany('Empresa Uno', '1001');
        $this->company->users()->attach($this->user->id, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);
        $this->period = $this->createPeriod($this->company);
        $this->asset = $this->createAccount($this->company, '1.1.01', 'Caja deudora', 'asset', 'debit');
        $this->liability = $this->createAccount($this->company, '2.1.01', 'Proveedor acreedor', 'liability', 'credit');
        $this->income = $this->createAccount($this->company, '4.1.01', 'Ingresos', 'income', 'credit');
        $this->expense = $this->createAccount($this->company, '5.1.01', 'Gastos', 'expense', 'debit');

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    public function test_debit_nature_account_with_credit_balance_blocks_closing(): void
    {
        $this->postedEntry([[$this->expense, '100.00', '0.00'], [$this->asset, '0.00', '100.00']]);

        $this->closePeriod()->assertSessionHasErrors([
            'accounting_period' => 'No se puede cerrar el período porque existen cuentas con saldo contrario. Revise las observaciones del Balance General.',
        ]);

        $this->assertPeriodRemainsOpen();
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_entry_lines', 2);
    }

    public function test_credit_nature_account_with_debit_balance_blocks_closing(): void
    {
        $this->postedEntry([[$this->liability, '100.00', '0.00'], [$this->income, '0.00', '100.00']]);

        $this->closePeriod()->assertSessionHasErrors('accounting_period');
        $this->assertPeriodRemainsOpen();
    }

    public function test_balance_general_identifies_observed_accounts_and_amounts(): void
    {
        $this->postedEntry([[$this->expense, '125.50', '0.00'], [$this->asset, '0.00', '125.50']]);

        $response = $this->get(route('accounting.balance-sheet.index'))->assertOk();

        $this->assertSame('balanced_with_observations', $response->viewData('balanceStatus'));
        $this->assertSame(1, $response->viewData('observationCount'));
        $response->assertSee('Balance cuadrado con observaciones')
            ->assertSee('Caja deudora')
            ->assertSee('1.1.01')
            ->assertSee('Deudora')
            ->assertSee('GTQ 125.50');
    }

    public function test_zero_difference_with_contrary_balances_is_not_marked_as_fully_valid(): void
    {
        $this->postedEntry([[$this->liability, '100.00', '0.00'], [$this->asset, '0.00', '100.00']]);

        $response = $this->get(route('accounting.balance-sheet.index'))->assertOk();

        $this->assertTrue($response->viewData('isBalanced'));
        $this->assertSame('0.00', $response->viewData('difference'));
        $this->assertSame('balanced_with_observations', $response->viewData('balanceStatus'));
        $this->assertSame(2, $response->viewData('observationCount'));
        $response->assertSee('text-bg-warning', false);
    }

    public function test_observations_disappear_after_correcting_entries(): void
    {
        $this->postedEntry([[$this->expense, '100.00', '0.00'], [$this->asset, '0.00', '100.00']]);
        $this->postedEntry([[$this->asset, '100.00', '0.00'], [$this->income, '0.00', '100.00']]);

        $response = $this->get(route('accounting.balance-sheet.index'))->assertOk();

        $this->assertSame('balanced', $response->viewData('balanceStatus'));
        $this->assertSame(0, $response->viewData('observationCount'));
        $response->assertDontSee('balance-sheet-observations', false);
    }

    public function test_valid_period_without_contrary_balances_can_be_closed(): void
    {
        $this->postedEntry([[$this->asset, '200.00', '0.00'], [$this->income, '0.00', '200.00']]);

        $this->closePeriod()->assertSessionHasNoErrors();

        $this->assertSame(AccountingPeriodStatus::CLOSED, $this->period->refresh()->status);
    }

    public function test_other_company_contrary_balances_do_not_affect_audit_or_closing(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos', '1002');
        $otherPeriod = $this->createPeriod($otherCompany);
        $otherAsset = $this->createAccount($otherCompany, '1.1.01', 'Caja ajena', 'asset', 'debit');
        $otherExpense = $this->createAccount($otherCompany, '5.1.01', 'Gasto ajeno', 'expense', 'debit');
        $this->postedEntry([[$otherExpense, '900.00', '0.00'], [$otherAsset, '0.00', '900.00']], $otherPeriod);

        $response = $this->get(route('accounting.balance-sheet.index'))->assertOk();
        $this->assertSame(0, $response->viewData('observationCount'));
        $response->assertDontSee('Caja ajena');

        $this->closePeriod()->assertSessionHasNoErrors();
        $this->assertSame(AccountingPeriodStatus::CLOSED, $this->period->refresh()->status);
    }

    private function closePeriod()
    {
        return $this->from(route('accounting-periods.index'))->post(route('accounting-periods.close', $this->period));
    }

    private function assertPeriodRemainsOpen(): void
    {
        $this->period->refresh();
        $this->assertSame(AccountingPeriodStatus::OPEN, $this->period->status);
        $this->assertNull($this->period->closed_at);
        $this->assertNull($this->period->closed_by);
    }

    private function postedEntry(array $lines, ?AccountingPeriod $period = null): JournalEntry
    {
        $period ??= $this->period;
        $number = JournalEntry::where('company_id', $period->company_id)->where('accounting_period_id', $period->id)->max('number');
        $entry = JournalEntry::create(['company_id' => $period->company_id, 'accounting_period_id' => $period->id, 'number' => ((int) $number + 1), 'entry_date' => '2026-01-15', 'description' => 'Movimiento auditado', 'status' => JournalEntryStatus::POSTED, 'created_by' => $this->user->id]);

        foreach ($lines as $index => [$account, $debit, $credit]) {
            $entry->lines()->create(['account_id' => $account->id, 'debit' => $debit, 'credit' => $credit, 'line_order' => $index + 1]);
        }

        return $entry;
    }

    private function createCompany(string $name, string $taxId): Company
    {
        return Company::create(['name' => $name, 'legal_name' => $name.' S.A.', 'tax_id' => $taxId, 'currency' => 'GTQ', 'status' => 'ACTIVE']);
    }

    private function createPeriod(Company $company): AccountingPeriod
    {
        return AccountingPeriod::create(['company_id' => $company->id, 'name' => 'Enero 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => AccountingPeriodStatus::OPEN]);
    }

    private function createAccount(Company $company, string $code, string $name, string $type, string $nature): Account
    {
        return Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $name, 'account_type' => $type, 'nature' => $nature, 'allows_entries' => true, 'level' => 1, 'is_active' => true]);
    }
}
