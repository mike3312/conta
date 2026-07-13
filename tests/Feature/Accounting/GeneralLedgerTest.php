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

class GeneralLedgerTest extends TestCase
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

    public function test_user_can_view_general_ledger_for_active_company(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-15');

        $this->get(route('accounting.general-ledger.index'))
            ->assertOk()
            ->assertSee('Libro Mayor')
            ->assertSee('Caja');
    }

    public function test_movements_from_other_companies_are_not_shown(): void
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
            '100.00',
            'Movimiento ajeno',
            $otherCompany,
            $otherPeriod
        );

        $this->get(route('accounting.general-ledger.index'))
            ->assertSee('Caja')
            ->assertDontSee('Caja ajena')
            ->assertDontSee('Movimiento ajeno');
    }

    public function test_only_posted_entries_are_included(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10', description: 'Contabilizada');
        $this->createEntry(JournalEntryStatus::DRAFT, null, '2026-01-11', description: 'Borrador');
        $this->createEntry(JournalEntryStatus::VOIDED, 2, '2026-01-12', description: 'Anulada');

        $response = $this->get(route('accounting.general-ledger.index'));
        $ledger = $this->ledgerFor($response, $this->debitAccount);

        $this->assertCount(1, $ledger['movements']);
        $this->assertSame('Contabilizada', $ledger['movements']->first()['entry_description']);
    }

    public function test_movements_are_grouped_by_account(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-15');

        $response = $this->get(route('accounting.general-ledger.index'));
        $debitLedger = $this->ledgerFor($response, $this->debitAccount);
        $creditLedger = $this->ledgerFor($response, $this->creditAccount);

        $this->assertCount(1, $debitLedger['movements']);
        $this->assertCount(1, $creditLedger['movements']);
        $this->assertSame('100.00', $debitLedger['total_debit']);
        $this->assertSame('100.00', $creditLedger['total_credit']);
    }

    public function test_movements_are_ordered_chronologically_then_by_number(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 3, '2026-01-20');
        $this->createEntry(JournalEntryStatus::POSTED, 2, '2026-01-10');
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10');

        $ledger = $this->ledgerFor(
            $this->get(route('accounting.general-ledger.index')),
            $this->debitAccount
        );

        $this->assertSame([1, 2, 3], $ledger['movements']->pluck('number')->map(fn ($value) => (int) $value)->all());
    }

    public function test_debit_credit_totals_and_final_balance_are_correct(): void
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

        $ledger = $this->ledgerFor(
            $this->get(route('accounting.general-ledger.index')),
            $this->debitAccount
        );

        $this->assertSame('100.25', $ledger['total_debit']);
        $this->assertSame('30.25', $ledger['total_credit']);
        $this->assertSame('70.00', $ledger['final_balance']);
    }

    public function test_running_balance_is_calculated_movement_by_movement(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10', amount: '100.00');
        $this->createEntry(
            JournalEntryStatus::POSTED,
            2,
            '2026-01-15',
            $this->creditAccount,
            $this->debitAccount,
            '30.00'
        );

        $ledger = $this->ledgerFor(
            $this->get(route('accounting.general-ledger.index')),
            $this->debitAccount
        );

        $this->assertSame(['100.00', '70.00'], $ledger['movements']->pluck('balance')->all());
    }

    public function test_previous_balance_uses_posted_movements_before_start_date(): void
    {
        $previousPeriod = $this->createPeriod($this->company, 'Diciembre 2025', '2025-12-01', '2025-12-31');

        $this->createEntry(
            JournalEntryStatus::POSTED,
            1,
            '2025-12-20',
            amount: '40.00',
            period: $previousPeriod
        );
        $this->createEntry(JournalEntryStatus::POSTED, 2, '2026-01-10', amount: '100.00');

        $ledger = $this->ledgerFor(
            $this->get(route('accounting.general-ledger.index', [
                'date_from' => '2026-01-01',
                'date_to' => '2026-01-31',
            ])),
            $this->debitAccount
        );

        $this->assertSame('40.00', $ledger['previous_balance']);
        $this->assertSame('140.00', $ledger['movements']->first()['balance']);
        $this->assertCount(1, $ledger['movements']);
    }

    public function test_debit_and_credit_balances_are_presented_without_negative_values(): void
    {
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10', amount: '75.00');

        $response = $this->get(route('accounting.general-ledger.index'));
        $debitLedger = $this->ledgerFor($response, $this->debitAccount);
        $creditLedger = $this->ledgerFor($response, $this->creditAccount);

        $this->assertSame('75.00', $debitLedger['final_balance']);
        $this->assertSame('Deudor', $debitLedger['final_balance_type']);
        $this->assertSame('75.00', $creditLedger['final_balance']);
        $this->assertSame('Acreedor', $creditLedger['final_balance_type']);
        $this->assertTrue($debitLedger['is_normal_balance']);
        $this->assertTrue($creditLedger['is_normal_balance']);
    }

    public function test_balance_contrary_to_account_nature_is_identified(): void
    {
        $this->createEntry(
            JournalEntryStatus::POSTED,
            1,
            '2026-01-10',
            $this->creditAccount,
            $this->debitAccount,
            '50.00'
        );

        $response = $this->get(route('accounting.general-ledger.index'));
        $assetLedger = $this->ledgerFor($response, $this->debitAccount);
        $incomeLedger = $this->ledgerFor($response, $this->creditAccount);

        $this->assertSame('Acreedor', $assetLedger['final_balance_type']);
        $this->assertFalse($assetLedger['is_normal_balance']);
        $this->assertSame('Deudor', $incomeLedger['final_balance_type']);
        $this->assertFalse($incomeLedger['is_normal_balance']);
    }

    public function test_date_period_and_specific_account_filters_work(): void
    {
        $february = $this->createPeriod($this->company, 'Febrero 2026', '2026-02-01', '2026-02-28');
        $this->createEntry(JournalEntryStatus::POSTED, 1, '2026-01-10', description: 'Enero');
        $this->createEntry(
            JournalEntryStatus::POSTED,
            1,
            '2026-02-10',
            description: 'Febrero',
            period: $february
        );

        $response = $this->get(route('accounting.general-ledger.index', [
            'accounting_period_id' => $february->id,
            'account_id' => $this->debitAccount->id,
        ]));
        $ledger = $this->ledgerFor($response, $this->debitAccount);

        $this->assertCount(1, $ledger['movements']);
        $this->assertSame('Febrero', $ledger['movements']->first()['entry_description']);
        $this->assertSame('2026-02-01', $response->viewData('filters')['date_from']);
        $this->assertSame('2026-02-28', $response->viewData('filters')['date_to']);
    }

    public function test_accounts_and_periods_from_other_company_are_rejected(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos');
        $otherPeriod = $this->createPeriod($otherCompany, 'Enero 2026', '2026-01-01', '2026-01-31');
        $otherAccount = $this->createAccount($otherCompany, '1.1.01', 'Cuenta ajena', 'asset', 'debit');

        $this->get(route('accounting.general-ledger.index', [
            'accounting_period_id' => $otherPeriod->id,
            'account_id' => $otherAccount->id,
        ]))->assertSessionHasErrors(['accounting_period_id', 'account_id']);
    }

    public function test_account_is_not_split_between_pages(): void
    {
        $commonCredit = $this->createAccount($this->company, '0.1', 'Contrapartida común', 'liability', 'credit');

        foreach (range(1, 11) as $number) {
            $debit = $this->createAccount(
                $this->company,
                '1.2.'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                "Cuenta $number",
                'asset',
                'debit'
            );
            $this->createEntry(JournalEntryStatus::POSTED, $number, '2026-01-15', $debit, $commonCredit);
        }

        $response = $this->get(route('accounting.general-ledger.index'));
        $ledger = $this->ledgerFor($response, $commonCredit);

        $this->assertCount(10, $response->viewData('ledgerAccounts')->items());
        $this->assertCount(11, $ledger['movements']);
    }

    public function test_accounts_without_movements_can_be_included_explicitly(): void
    {
        $emptyAccount = $this->createAccount($this->company, '1.9.99', 'Cuenta sin movimientos', 'asset', 'debit');

        $withoutEmptyAccounts = $this->get(route('accounting.general-ledger.index'));

        $this->assertFalse(
            collect($withoutEmptyAccounts->viewData('ledgerAccounts')->items())
                ->contains(fn ($ledger) => $ledger['account']->is($emptyAccount))
        );

        $withEmptyAccounts = $this->get(route('accounting.general-ledger.index', ['show_without_movements' => 1]));

        $withEmptyAccounts->assertOk();
        $this->assertTrue(
            collect($withEmptyAccounts->viewData('ledgerAccounts')->items())
                ->contains(fn ($ledger) => $ledger['account']->is($emptyAccount))
        );
    }

    public function test_view_works_when_there_are_no_movements(): void
    {
        $this->get(route('accounting.general-ledger.index'))
            ->assertOk()
            ->assertSee('No existen cuentas con movimientos que coincidan con los filtros seleccionados.');
    }

    public function test_company_without_access_in_session_is_replaced(): void
    {
        $otherCompany = $this->createCompany('Empresa sin acceso');

        $this->withSession(['company_id' => $otherCompany->id])
            ->get(route('accounting.general-ledger.index'))
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

    private function createPeriod(
        Company $company,
        string $name,
        string $startDate,
        string $endDate
    ): AccountingPeriod {
        return AccountingPeriod::create([
            'company_id' => $company->id,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => AccountingPeriodStatus::OPEN->value,
        ]);
    }

    private function createAccount(
        Company $company,
        string $code,
        string $name,
        string $type,
        string $nature
    ): Account {
        return Account::create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
            'account_type' => $type,
            'nature' => $nature,
            'allows_entries' => true,
            'level' => 1,
            'is_active' => true,
        ]);
    }

    private function createEntry(
        JournalEntryStatus $status,
        ?int $number,
        string $entryDate,
        ?Account $debitAccount = null,
        ?Account $creditAccount = null,
        string $amount = '100.00',
        string $description = 'Movimiento de prueba',
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

    private function ledgerFor($response, Account $account): array
    {
        return collect($response->viewData('ledgerAccounts')->items())
            ->first(fn ($ledger) => $ledger['account']->is($account));
    }
}
