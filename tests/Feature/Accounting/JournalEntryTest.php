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

class JournalEntryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    private AccountingPeriod $period;

    private Account $debitAccount;

    private Account $creditAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'name' => 'Tenant de pruebas',
            'email' => 'tenant@example.com',
            'status' => 'ACTIVE',
        ]);

        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->company = $this->createCompany($tenant, 'Empresa Uno');
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

    public function test_company_cannot_view_an_entry_from_another_company(): void
    {
        $otherCompany = $this->createCompany($this->company->tenant, 'Empresa Dos');
        $otherPeriod = $this->createPeriod($otherCompany);
        $entry = $this->createDraft($otherCompany, $otherPeriod, []);

        $this->get(route('journal-entries.show', $entry))->assertForbidden();
    }

    public function test_company_cannot_use_an_account_from_another_company(): void
    {
        $otherCompany = $this->createCompany($this->company->tenant, 'Empresa Dos');
        $otherAccount = $this->createAccount($otherCompany, '1.1.01', 'Caja ajena');

        $response = $this->post(route('journal-entries.store'), $this->payload([
            $this->line($otherAccount, '10.00', '0.00'),
        ]));

        $response->assertSessionHasErrors('lines.0.account_id');
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_unbalanced_entry_cannot_be_posted(): void
    {
        $entry = $this->createDraft($this->company, $this->period, [
            $this->line($this->debitAccount, '100.00', '0.00'),
            $this->line($this->creditAccount, '0.00', '90.00'),
        ]);

        $this->post(route('journal-entries.post', $entry))->assertSessionHasErrors('lines');
        $this->assertSame(JournalEntryStatus::DRAFT, $entry->refresh()->status);
    }

    public function test_entry_with_fewer_than_two_lines_cannot_be_posted(): void
    {
        $entry = $this->createDraft($this->company, $this->period, [
            $this->line($this->debitAccount, '100.00', '0.00'),
        ]);

        $this->post(route('journal-entries.post', $entry))->assertSessionHasErrors('lines');
        $this->assertSame(JournalEntryStatus::DRAFT, $entry->refresh()->status);
    }

    public function test_inactive_account_cannot_be_used(): void
    {
        $inactiveAccount = $this->createAccount($this->company, '1.1.02', 'Cuenta inactiva', false);

        $this->post(route('journal-entries.store'), $this->payload([
            $this->line($inactiveAccount, '10.00', '0.00'),
        ]))->assertSessionHasErrors('lines.0.account_id');
    }

    public function test_header_account_cannot_be_used(): void
    {
        $headerAccount = $this->createAccount($this->company, '1.1', 'Activo corriente', true, false);

        $this->post(route('journal-entries.store'), $this->payload([
            $this->line($headerAccount, '10.00', '0.00'),
        ]))->assertSessionHasErrors('lines.0.account_id');
    }

    public function test_entry_cannot_be_posted_in_a_closed_period(): void
    {
        $entry = $this->createBalancedDraft();
        $this->period->update(['status' => AccountingPeriodStatus::CLOSED->value]);

        $this->post(route('journal-entries.post', $entry))->assertSessionHasErrors('accounting_period_id');
        $this->assertSame(JournalEntryStatus::DRAFT, $entry->refresh()->status);
    }

    public function test_entry_cannot_be_posted_with_date_outside_period(): void
    {
        $entry = $this->createBalancedDraft('2026-02-01');

        $this->post(route('journal-entries.post', $entry))->assertSessionHasErrors('entry_date');
        $this->assertSame(JournalEntryStatus::DRAFT, $entry->refresh()->status);
    }

    public function test_valid_entry_can_be_posted(): void
    {
        $entry = $this->createBalancedDraft();

        $this->post(route('journal-entries.post', $entry))->assertRedirect(route('journal-entries.show', $entry));

        $entry->refresh();
        $this->assertSame(JournalEntryStatus::POSTED, $entry->status);
        $this->assertSame($this->user->id, $entry->posted_by);
        $this->assertNotNull($entry->posted_at);
    }

    public function test_posting_assigns_a_correlative_number_by_company_and_period(): void
    {
        $first = $this->createBalancedDraft();
        $second = $this->createBalancedDraft();

        $this->post(route('journal-entries.post', $first));
        $this->post(route('journal-entries.post', $second));

        $this->assertSame(1, (int) $first->refresh()->number);
        $this->assertSame(2, (int) $second->refresh()->number);
    }

    public function test_posted_entry_cannot_be_edited(): void
    {
        $entry = $this->createBalancedDraft();
        $this->post(route('journal-entries.post', $entry));

        $this->get(route('journal-entries.edit', $entry))
            ->assertRedirect(route('journal-entries.show', $entry));

        $this->put(route('journal-entries.update', $entry), $this->payload([
            $this->line($this->debitAccount, '20.00', '0.00'),
            $this->line($this->creditAccount, '0.00', '20.00'),
        ]))->assertSessionHasErrors('journal_entry');
    }

    public function test_posted_entry_cannot_be_deleted(): void
    {
        $entry = $this->createBalancedDraft();
        $this->post(route('journal-entries.post', $entry));

        $this->delete(route('journal-entries.destroy', $entry))->assertSessionHasErrors('journal_entry');
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
    }

    public function test_posted_entry_can_be_voided_with_a_reason(): void
    {
        $entry = $this->createBalancedDraft();
        $this->post(route('journal-entries.post', $entry));

        $this->post(route('journal-entries.void', $entry), [
            'void_reason' => 'Documento registrado incorrectamente.',
        ])->assertRedirect(route('journal-entries.show', $entry));

        $entry->refresh();
        $this->assertSame(JournalEntryStatus::VOIDED, $entry->status);
        $this->assertSame('Documento registrado incorrectamente.', $entry->void_reason);
        $this->assertSame($this->user->id, $entry->voided_by);
    }

    public function test_voided_entry_keeps_its_lines_and_number(): void
    {
        $entry = $this->createBalancedDraft();
        $this->post(route('journal-entries.post', $entry));
        $number = $entry->refresh()->number;
        $lineIds = $entry->lines()->pluck('id')->all();

        $this->post(route('journal-entries.void', $entry), ['void_reason' => 'Anulación de prueba.']);

        $this->assertSame($number, $entry->refresh()->number);
        $this->assertSame($lineIds, $entry->lines()->pluck('id')->all());
    }

    public function test_voided_entry_cannot_be_posted_again(): void
    {
        $entry = $this->createBalancedDraft();
        $this->post(route('journal-entries.post', $entry));
        $this->post(route('journal-entries.void', $entry), ['void_reason' => 'Anulación de prueba.']);

        $this->post(route('journal-entries.post', $entry))->assertSessionHasErrors('journal_entry');
        $this->assertSame(JournalEntryStatus::VOIDED, $entry->refresh()->status);
    }

    public function test_unbalanced_draft_can_be_saved(): void
    {
        $response = $this->post(route('journal-entries.store'), $this->payload([
            $this->line($this->debitAccount, '75.00', '0.00'),
            $this->line($this->creditAccount, '0.00', '10.00'),
        ]));

        $entry = JournalEntry::firstOrFail();
        $response->assertRedirect(route('journal-entries.show', $entry));
        $this->assertSame(JournalEntryStatus::DRAFT, $entry->status);
        $this->assertNull($entry->number);
    }

    public function test_debit_and_credit_are_balanced_safely_to_two_decimals(): void
    {
        $entry = $this->createDraft($this->company, $this->period, [
            $this->line($this->debitAccount, '0.30', '0.00'),
            $this->line($this->creditAccount, '0.00', '0.10'),
            $this->line($this->creditAccount, '0.00', '0.20'),
        ]);

        $this->post(route('journal-entries.post', $entry))->assertSessionHasNoErrors();
        $this->assertSame(JournalEntryStatus::POSTED, $entry->refresh()->status);
    }

    private function createCompany(Tenant $tenant, string $name): Company
    {
        return Company::create([
            'tenant_id' => $tenant->id,
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

    private function createAccount(
        Company $company,
        string $code,
        string $name,
        bool $active = true,
        bool $allowsEntries = true
    ): Account {
        return Account::create([
            'company_id' => $company->id,
            'code' => $code,
            'name' => $name,
            'account_type' => str_starts_with($code, '4') ? 'income' : 'asset',
            'nature' => str_starts_with($code, '4') ? 'credit' : 'debit',
            'allows_entries' => $allowsEntries,
            'level' => 1,
            'is_active' => $active,
        ]);
    }

    private function createBalancedDraft(string $entryDate = '2026-01-15'): JournalEntry
    {
        return $this->createDraft($this->company, $this->period, [
            $this->line($this->debitAccount, '100.00', '0.00'),
            $this->line($this->creditAccount, '0.00', '100.00'),
        ], $entryDate);
    }

    private function createDraft(
        Company $company,
        AccountingPeriod $period,
        array $lines,
        string $entryDate = '2026-01-15'
    ): JournalEntry {
        $entry = JournalEntry::create([
            'company_id' => $company->id,
            'accounting_period_id' => $period->id,
            'entry_date' => $entryDate,
            'description' => 'Póliza de prueba',
            'status' => JournalEntryStatus::DRAFT->value,
            'created_by' => $this->user->id,
        ]);

        foreach ($lines as $index => $line) {
            $entry->lines()->create($line + ['line_order' => $index + 1]);
        }

        return $entry;
    }

    private function payload(array $lines): array
    {
        return [
            'accounting_period_id' => $this->period->id,
            'entry_date' => '2026-01-15',
            'description' => 'Póliza desde formulario',
            'reference' => 'REF-001',
            'lines' => $lines,
        ];
    }

    private function line(Account $account, string $debit, string $credit): array
    {
        return [
            'account_id' => $account->id,
            'description' => null,
            'debit' => $debit,
            'credit' => $credit,
        ];
    }
}
