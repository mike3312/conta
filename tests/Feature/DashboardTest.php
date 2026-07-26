<?php

namespace Tests\Feature;

use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private Company $otherCompany;

    private AccountingPeriod $period;

    private AccountingPeriod $otherPeriod;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->company = $this->company('Empresa Uno', '1001');
        $this->otherCompany = $this->company('Empresa Dos', '1002');
        $this->attach($this->company);
        $this->attach($this->otherCompany);
        $this->period = $this->period($this->company);
        $this->otherPeriod = $this->period($this->otherCompany);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    public function test_dashboard_only_uses_the_active_company_in_cards_chart_and_activity(): void
    {
        $accounts = $this->accounts($this->company);
        $otherAccounts = $this->accounts($this->otherCompany);
        $this->entry($this->company, $this->period, 'Ingreso propio', JournalEntryStatus::POSTED, [
            [$accounts['asset'], '120.00', '0.00'], [$accounts['income'], '0.00', '120.00'],
        ]);
        $this->entry($this->otherCompany, $this->otherPeriod, 'Movimiento confidencial ajeno', JournalEntryStatus::POSTED, [
            [$otherAccounts['asset'], '999.00', '0.00'], [$otherAccounts['income'], '0.00', '999.00'],
        ]);

        $response = $this->get(route('dashboard'))->assertOk()->assertDontSee('Movimiento confidencial ajeno');
        $dashboard = $response->viewData('dashboard');
        $this->assertSame(120.0, $dashboard['metrics']['income']);
        $this->assertSame(120.0, $dashboard['metrics']['assets']);
        $this->assertSame([120.0], $dashboard['chart']['income']);
        $this->assertSame('Ingreso propio', $dashboard['recentEntries']->first()->description);
    }

    public function test_switching_company_updates_all_dashboard_information(): void
    {
        $first = $this->accounts($this->company);
        $second = $this->accounts($this->otherCompany);
        $this->entry($this->company, $this->period, 'Primera empresa', JournalEntryStatus::POSTED, [[$first['asset'], '50.00', '0.00'], [$first['income'], '0.00', '50.00']]);
        $this->entry($this->otherCompany, $this->otherPeriod, 'Segunda empresa', JournalEntryStatus::POSTED, [[$second['asset'], '275.00', '0.00'], [$second['income'], '0.00', '275.00']]);

        $this->post(route('companies.switch'), ['company_id' => $this->otherCompany->id])->assertRedirect();
        $response = $this->get(route('dashboard'))->assertOk()->assertSee('Segunda empresa')->assertDontSee('Primera empresa');
        $this->assertSame(275.0, $response->viewData('dashboard')['metrics']['income']);
    }

    public function test_company_without_movements_shows_zero_values(): void
    {
        $dashboard = $this->get(route('dashboard'))->assertOk()->viewData('dashboard');
        $this->assertSame(['income' => 0.0, 'expenses' => 0.0, 'net_result' => 0.0, 'assets' => 0.0], $dashboard['metrics']);
        $this->assertSame([0.0], $dashboard['chart']['income']);
    }

    public function test_draft_entries_do_not_affect_financial_totals(): void
    {
        $accounts = $this->accounts($this->company);
        $this->entry($this->company, $this->period, 'Borrador', JournalEntryStatus::DRAFT, [[$accounts['asset'], '800.00', '0.00'], [$accounts['income'], '0.00', '800.00']]);
        $dashboard = $this->get(route('dashboard'))->assertOk()->viewData('dashboard');
        $this->assertSame(0.0, $dashboard['metrics']['income']);
        $this->assertSame(0.0, $dashboard['metrics']['assets']);
    }

    public function test_net_result_equals_income_minus_expenses(): void
    {
        $accounts = $this->accounts($this->company);
        $this->entry($this->company, $this->period, 'Venta', JournalEntryStatus::POSTED, [[$accounts['asset'], '500.00', '0.00'], [$accounts['income'], '0.00', '500.00']]);
        $this->entry($this->company, $this->period, 'Gasto', JournalEntryStatus::POSTED, [[$accounts['expense'], '125.00', '0.00'], [$accounts['asset'], '0.00', '125.00']]);
        $metrics = $this->get(route('dashboard'))->assertOk()->viewData('dashboard')['metrics'];
        $this->assertSame($metrics['income'] - $metrics['expenses'], $metrics['net_result']);
        $this->assertSame(375.0, $metrics['net_result']);
    }

    private function company(string $name, string $taxId): Company
    {
        return Company::create(['name' => $name, 'legal_name' => $name.' S.A.', 'tax_id' => $taxId, 'currency' => 'GTQ', 'status' => 'ACTIVE']);
    }

    private function attach(Company $company): void
    {
        $company->users()->attach($this->user->id, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);
    }

    private function period(Company $company): AccountingPeriod
    {
        return AccountingPeriod::create(['company_id' => $company->id, 'name' => 'Enero 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => AccountingPeriodStatus::OPEN]);
    }

    private function accounts(Company $company): array
    {
        return [
            'asset' => $this->account($company, '1.1.01', 'Caja', 'asset', 'debit'),
            'income' => $this->account($company, '4.1.01', 'Ventas', 'income', 'credit'),
            'expense' => $this->account($company, '5.1.01', 'Gastos', 'expense', 'debit'),
        ];
    }

    private function account(Company $company, string $code, string $name, string $type, string $nature): Account
    {
        return Account::firstOrCreate(['company_id' => $company->id, 'code' => $code], ['name' => $name, 'account_type' => $type, 'nature' => $nature, 'allows_entries' => true, 'level' => 1, 'is_active' => true]);
    }

    private function entry(Company $company, AccountingPeriod $period, string $description, JournalEntryStatus $status, array $lines): JournalEntry
    {
        $number = JournalEntry::where('company_id', $company->id)->where('accounting_period_id', $period->id)->max('number');
        $entry = JournalEntry::create(['company_id' => $company->id, 'accounting_period_id' => $period->id, 'number' => $status === JournalEntryStatus::POSTED ? ((int) $number + 1) : null, 'entry_date' => '2026-01-15', 'description' => $description, 'status' => $status, 'created_by' => $this->user->id]);
        foreach ($lines as $index => [$account, $debit, $credit]) {
            $entry->lines()->create(['account_id' => $account->id, 'debit' => $debit, 'credit' => $credit, 'line_order' => $index + 1]);
        }

        return $entry;
    }
}
