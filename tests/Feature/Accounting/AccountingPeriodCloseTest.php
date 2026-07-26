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

class AccountingPeriodCloseTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private AccountingPeriod $period;

    private Account $asset;

    private Account $income;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->company = $this->createCompany('Empresa Uno', '1001');
        $this->attach($this->company);
        $this->period = $this->createPeriod($this->company);
        $this->asset = $this->createAccount($this->company, '1.1.01', 'Caja', 'asset', 'debit');
        $this->income = $this->createAccount($this->company, '4.1.01', 'Ingresos', 'income', 'credit');

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    public function test_open_period_without_movements_can_be_closed_and_audited(): void
    {
        $this->closePeriod($this->period)
            ->assertRedirect(route('accounting-periods.index'))
            ->assertSessionHas('success', 'El período contable fue cerrado correctamente.');

        $this->period->refresh();
        $this->assertSame(AccountingPeriodStatus::CLOSED, $this->period->status);
        $this->assertNotNull($this->period->closed_at);
        $this->assertSame($this->user->id, $this->period->closed_by);
    }

    public function test_period_with_posted_balanced_entries_can_be_closed(): void
    {
        $entry = $this->createEntry($this->period, JournalEntryStatus::POSTED);

        $this->closePeriod($this->period)->assertSessionHasNoErrors();

        $this->assertSame(AccountingPeriodStatus::CLOSED, $this->period->refresh()->status);
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
        $this->assertDatabaseCount('journal_entry_lines', 2);
    }

    public function test_period_from_another_company_cannot_be_closed(): void
    {
        $otherCompany = $this->createCompany('Empresa Dos', '1002');
        $this->attach($otherCompany);
        $otherPeriod = $this->createPeriod($otherCompany);

        $this->post(route('accounting-periods.close', $otherPeriod))->assertForbidden();
        $this->assertSame(AccountingPeriodStatus::OPEN, $otherPeriod->refresh()->status);
    }

    public function test_closed_period_cannot_be_closed_again(): void
    {
        $this->period->update(['status' => AccountingPeriodStatus::CLOSED, 'closed_at' => now(), 'closed_by' => $this->user->id]);

        $this->closePeriod($this->period)
            ->assertSessionHasErrors(['accounting_period' => 'El período contable ya se encuentra cerrado.']);
    }

    public function test_period_with_draft_entries_cannot_be_closed(): void
    {
        $this->createEntry($this->period, JournalEntryStatus::DRAFT);

        $this->closePeriod($this->period)
            ->assertSessionHasErrors(['accounting_period' => 'No se puede cerrar el período porque contiene pólizas en borrador.']);

        $this->assertSame(AccountingPeriodStatus::OPEN, $this->period->refresh()->status);
    }

    public function test_period_with_unbalanced_posted_entries_cannot_be_closed(): void
    {
        $this->createEntry($this->period, JournalEntryStatus::POSTED, '2026-01-15', '100.00', '90.00');

        $this->closePeriod($this->period)
            ->assertSessionHasErrors(['accounting_period' => 'No se puede cerrar el período porque contiene pólizas descuadradas.']);
    }

    public function test_period_with_entries_outside_its_dates_cannot_be_closed(): void
    {
        $this->createEntry($this->period, JournalEntryStatus::POSTED, '2026-02-01');

        $this->closePeriod($this->period)
            ->assertSessionHasErrors(['accounting_period' => 'No se puede cerrar el período porque contiene pólizas fuera de sus fechas.']);
    }

    public function test_draft_cannot_be_created_in_a_closed_period(): void
    {
        $this->markClosed($this->period);

        $this->post(route('journal-entries.store'), $this->entryPayload())
            ->assertSessionHasErrors('accounting_period_id');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_entry_and_its_lines_cannot_be_edited_in_a_closed_period(): void
    {
        $entry = $this->createEntry($this->period, JournalEntryStatus::DRAFT);
        $originalLine = $entry->lines()->firstOrFail();
        $this->markClosed($this->period);

        $this->put(route('journal-entries.update', $entry), $this->entryPayload('250.00'))
            ->assertSessionHasErrors('accounting_period_id');

        $this->assertSame('100.00', $originalLine->refresh()->debit);
        $this->assertSame('Movimiento de prueba', $entry->refresh()->description);
    }

    public function test_draft_cannot_be_deleted_in_a_closed_period(): void
    {
        $entry = $this->createEntry($this->period, JournalEntryStatus::DRAFT);
        $this->markClosed($this->period);

        $this->delete(route('journal-entries.destroy', $entry))->assertSessionHasErrors('accounting_period_id');
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
        $this->assertDatabaseCount('journal_entry_lines', 2);
    }

    public function test_entry_cannot_be_posted_or_voided_in_a_closed_period(): void
    {
        $draft = $this->createEntry($this->period, JournalEntryStatus::DRAFT);
        $posted = $this->createEntry($this->period, JournalEntryStatus::POSTED);
        $this->markClosed($this->period);

        $this->post(route('journal-entries.post', $draft))->assertSessionHasErrors('accounting_period_id');
        $this->post(route('journal-entries.void', $posted), ['void_reason' => 'Corrección contable.'])->assertSessionHasErrors('accounting_period_id');
        $this->assertSame(JournalEntryStatus::DRAFT, $draft->refresh()->status);
        $this->assertSame(JournalEntryStatus::POSTED, $posted->refresh()->status);
    }

    public function test_entry_cannot_be_moved_out_of_a_closed_period(): void
    {
        $entry = $this->createEntry($this->period, JournalEntryStatus::DRAFT);
        $newPeriod = AccountingPeriod::create(['company_id' => $this->company->id, 'name' => 'Febrero 2026', 'start_date' => '2026-02-01', 'end_date' => '2026-02-28', 'status' => AccountingPeriodStatus::OPEN]);
        $this->markClosed($this->period);

        $payload = $this->entryPayload();
        $payload['accounting_period_id'] = $newPeriod->id;
        $payload['entry_date'] = '2026-02-15';

        $this->put(route('journal-entries.update', $entry), $payload)->assertSessionHasErrors('accounting_period_id');
        $this->assertSame($this->period->id, $entry->refresh()->accounting_period_id);
    }

    public function test_reports_remain_available_after_closing(): void
    {
        $this->closePeriod($this->period)->assertSessionHasNoErrors();

        foreach (['accounting.daily-book.index', 'accounting.general-ledger.index', 'accounting.income-statement.index', 'accounting.balance-sheet.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_period_list_keeps_other_company_information_isolated(): void
    {
        $otherCompany = $this->createCompany('Empresa Confidencial', '1003');
        $otherPeriod = $this->createPeriod($otherCompany, 'Período confidencial');
        $this->markClosed($otherPeriod);

        $this->get(route('accounting-periods.index'))
            ->assertOk()
            ->assertDontSee('Período confidencial')
            ->assertSee($this->period->name);
    }

    private function closePeriod(AccountingPeriod $period)
    {
        return $this->from(route('accounting-periods.index'))->post(route('accounting-periods.close', $period));
    }

    private function markClosed(AccountingPeriod $period): void
    {
        $period->update(['status' => AccountingPeriodStatus::CLOSED, 'closed_at' => now(), 'closed_by' => $this->user->id]);
    }

    private function createEntry(AccountingPeriod $period, JournalEntryStatus $status, string $date = '2026-01-15', string $debit = '100.00', string $credit = '100.00'): JournalEntry
    {
        $number = JournalEntry::where('company_id', $period->company_id)->where('accounting_period_id', $period->id)->max('number');
        $entry = JournalEntry::create(['company_id' => $period->company_id, 'accounting_period_id' => $period->id, 'number' => $status === JournalEntryStatus::POSTED ? ((int) $number + 1) : null, 'entry_date' => $date, 'description' => 'Movimiento de prueba', 'status' => $status, 'created_by' => $this->user->id]);
        $entry->lines()->create(['account_id' => $this->asset->id, 'debit' => $debit, 'credit' => '0.00', 'line_order' => 1]);
        $entry->lines()->create(['account_id' => $this->income->id, 'debit' => '0.00', 'credit' => $credit, 'line_order' => 2]);

        return $entry;
    }

    private function entryPayload(string $amount = '100.00'): array
    {
        return ['accounting_period_id' => $this->period->id, 'entry_date' => '2026-01-15', 'description' => 'Movimiento actualizado', 'reference' => null, 'lines' => [
            ['account_id' => $this->asset->id, 'description' => null, 'debit' => $amount, 'credit' => '0.00'],
            ['account_id' => $this->income->id, 'description' => null, 'debit' => '0.00', 'credit' => $amount],
        ]];
    }

    private function createCompany(string $name, string $taxId): Company
    {
        return Company::create(['name' => $name, 'legal_name' => $name.' S.A.', 'tax_id' => $taxId, 'status' => 'ACTIVE']);
    }

    private function attach(Company $company): void
    {
        $company->users()->attach($this->user->id, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);
    }

    private function createPeriod(Company $company, string $name = 'Enero 2026'): AccountingPeriod
    {
        return AccountingPeriod::create(['company_id' => $company->id, 'name' => $name, 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => AccountingPeriodStatus::OPEN]);
    }

    private function createAccount(Company $company, string $code, string $name, string $type, string $nature): Account
    {
        return Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $name, 'account_type' => $type, 'nature' => $nature, 'allows_entries' => true, 'level' => 1, 'is_active' => true]);
    }
}
