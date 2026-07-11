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

class DailyBookTest extends TestCase
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

        $this->period = $this->createPeriod($this->company);
        $this->debitAccount = $this->createAccount($this->company, '1.1.01', 'Caja');
        $this->creditAccount = $this->createAccount($this->company, '4.1.01', 'Ingresos');

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    public function test_authenticated_user_can_view_daily_book_for_active_company(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, 'Póliza visible');

        $this->get(route('accounting.daily-book.index'))
            ->assertOk()
            ->assertSee('Libro Diario')
            ->assertSee('Póliza visible');
    }

    public function test_entries_from_other_companies_are_not_shown(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos');
        $otherPeriod = $this->createPeriod($otherCompany);
        $otherDebit = $this->createAccount($otherCompany, '1.1.01', 'Caja ajena');
        $otherCredit = $this->createAccount($otherCompany, '4.1.01', 'Ingreso ajeno');

        $this->createEntry(JournalEntryStatus::POSTED, 1, 'Póliza propia');
        $this->createEntry(
            JournalEntryStatus::POSTED,
            1,
            'Póliza ajena',
            '2026-01-15',
            $otherCompany,
            $otherPeriod,
            $otherDebit,
            $otherCredit
        );

        $this->get(route('accounting.daily-book.index'))
            ->assertSee('Póliza propia')
            ->assertDontSee('Póliza ajena');
    }

    public function test_only_posted_entries_are_shown(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, 'Contabilizada');
        $this->createEntry(JournalEntryStatus::DRAFT, null, 'Borrador');
        $this->createEntry(JournalEntryStatus::VOIDED, 2, 'Anulada');

        $this->get(route('accounting.daily-book.index'))
            ->assertSee('Contabilizada')
            ->assertDontSee('Borrador')
            ->assertDontSee('Anulada');
    }

    public function test_date_filters_work(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, 'Dentro del rango', '2026-01-10');
        $this->createEntry(JournalEntryStatus::POSTED, 2, 'Fuera del rango', '2026-02-10');

        $this->get(route('accounting.daily-book.index', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
        ]))
            ->assertOk()
            ->assertSee('Dentro del rango')
            ->assertDontSee('Fuera del rango');
    }

    public function test_account_filter_works_without_hiding_other_lines_of_entry(): void
    {
        $otherDebit = $this->createAccount($this->company, '1.1.02', 'Bancos');

        $this->createEntry(JournalEntryStatus::POSTED, 1, 'Movimiento de caja');
        $this->createEntry(
            JournalEntryStatus::POSTED,
            2,
            'Movimiento bancario',
            '2026-01-16',
            debitAccount: $otherDebit
        );

        $this->get(route('accounting.daily-book.index', ['account_id' => $this->debitAccount->id]))
            ->assertOk()
            ->assertSee('Movimiento de caja')
            ->assertSee('Ingresos')
            ->assertDontSee('Movimiento bancario');
    }

    public function test_general_debit_and_credit_totals_are_calculated_correctly(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, 'Primera', amount: '100.25');
        $this->createEntry(JournalEntryStatus::POSTED, 2, 'Segunda', amount: '25.75');

        $response = $this->get(route('accounting.daily-book.index'));

        $response->assertOk();
        $this->assertSame(12600, $this->toCents($response->viewData('totalDebit')));
        $this->assertSame(12600, $this->toCents($response->viewData('totalCredit')));
        $this->assertTrue($response->viewData('isBalanced'));
    }

    public function test_pagination_keeps_all_lines_of_each_entry_together(): void
    {
        foreach (range(1, 16) as $number) {
            $this->createEntry(JournalEntryStatus::POSTED, $number, "Póliza $number");
        }

        $response = $this->get(route('accounting.daily-book.index'));
        $entries = $response->viewData('journalEntries');

        $response->assertOk();
        $this->assertCount(15, $entries->items());

        foreach ($entries as $entry) {
            $this->assertCount(2, $entry->lines);
        }
    }

    public function test_period_from_other_company_cannot_be_used(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos');
        $otherPeriod = $this->createPeriod($otherCompany);

        $this->get(route('accounting.daily-book.index', [
            'accounting_period_id' => $otherPeriod->id,
        ]))->assertSessionHasErrors('accounting_period_id');
    }

    public function test_account_from_other_company_cannot_be_used(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos');
        $otherAccount = $this->createAccount($otherCompany, '1.1.01', 'Cuenta ajena');

        $this->get(route('accounting.daily-book.index', [
            'account_id' => $otherAccount->id,
        ]))->assertSessionHasErrors('account_id');
    }

    public function test_view_responds_correctly_without_entries(): void
    {
        $this->get(route('accounting.daily-book.index'))
            ->assertOk()
            ->assertSee('No existen pólizas contabilizadas que coincidan con los filtros seleccionados.');
    }

    public function test_user_without_access_to_active_company_is_forbidden(): void
    {
        $otherCompany = $this->createCompany('Empresa sin acceso');

        $this->withSession(['company_id' => $otherCompany->id])
            ->get(route('accounting.daily-book.index'))
            ->assertForbidden();
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

    private function createPeriod(Company $company): AccountingPeriod
    {
        return AccountingPeriod::create([
            'company_id' => $company->id,
            'name' => 'Enero 2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-01-31',
            'status' => AccountingPeriodStatus::OPEN->value,
        ]);
    }

    private function createAccount(Company $company, string $code, string $name): Account
    {
        return Account::create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
            'account_type' => str_starts_with($code, '4') ? 'income' : 'asset',
            'nature' => str_starts_with($code, '4') ? 'credit' : 'debit',
            'allows_entries' => true,
            'level' => 1,
            'is_active' => true,
        ]);
    }

    private function createEntry(
        JournalEntryStatus $status,
        ?int $number,
        string $description,
        string $entryDate = '2026-01-15',
        ?Company $company = null,
        ?AccountingPeriod $period = null,
        ?Account $debitAccount = null,
        ?Account $creditAccount = null,
        string $amount = '100.00'
    ): JournalEntry {
        $company ??= $this->company;
        $period ??= $this->period;
        $debitAccount ??= $this->debitAccount;
        $creditAccount ??= $this->creditAccount;

        $entry = JournalEntry::create([
            'company_id' => $company->id,
            'accounting_period_id' => $period->id,
            'number' => $number,
            'entry_date' => $entryDate,
            'description' => $description,
            'status' => $status->value,
            'created_by' => $this->user->id,
            'posted_by' => $status === JournalEntryStatus::POSTED ? $this->user->id : null,
            'posted_at' => $status === JournalEntryStatus::POSTED ? now() : null,
        ]);

        $entry->lines()->create([
            'account_id' => $debitAccount->id,
            'description' => 'Cargo',
            'debit' => $amount,
            'credit' => '0.00',
            'line_order' => 1,
        ]);

        $entry->lines()->create([
            'account_id' => $creditAccount->id,
            'description' => 'Abono',
            'debit' => '0.00',
            'credit' => $amount,
            'line_order' => 2,
        ]);

        return $entry;
    }

    private function toCents(string $amount): int
    {
        [$whole, $decimals] = array_pad(explode('.', $amount, 2), 2, '0');

        return ((int) $whole * 100) + (int) str_pad(substr($decimals, 0, 2), 2, '0');
    }
}
