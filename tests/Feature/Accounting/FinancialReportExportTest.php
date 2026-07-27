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
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class FinancialReportExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    private Company $company;

    private AccountingPeriod $period;

    private Account $cash;

    private Account $income;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Tenant reportes', 'email' => 'reportes@example.com', 'status' => 'ACTIVE']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->company = $this->createCompany('Empresa Principal');
        $this->company->users()->attach($this->user, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);
        $this->period = $this->createPeriod($this->company);
        $this->cash = $this->createAccount($this->company, '1.1.01', 'Caja', 'asset', 'debit');
        $this->income = $this->createAccount($this->company, '4.1.01', 'Ventas', 'income', 'credit');
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    public function test_all_financial_reports_export_pdf_and_excel_with_expected_names(): void
    {
        $this->createEntry($this->company, $this->period, $this->cash, $this->income, 'Venta enero', '2026-01-15', '100.00');
        $reports = [
            ['accounting.general-ledger', 'libro-mayor'],
            ['accounting.trial-balance', 'balance-comprobacion'],
            ['accounting.income-statement', 'estado-resultados'],
            ['accounting.balance-sheet', 'balance-general'],
        ];

        foreach ($reports as [$route, $slug]) {
            $pdf = $this->get(route($route.'.export.pdf'));
            $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringContainsString($slug.'-empresa-principal-', $pdf->headers->get('content-disposition'));
            $this->assertStringStartsWith('%PDF', $pdf->getContent());

            $excel = $this->get(route($route.'.export.excel'));
            $excel->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->assertStringContainsString($slug.'-empresa-principal-', $excel->headers->get('content-disposition'));
            $rows = $this->xlsxRows($excel);
            $this->assertContains('Empresa Principal', $this->flatten($rows));
        }
    }

    public function test_exports_preserve_filters_and_do_not_leak_another_company(): void
    {
        $this->createEntry($this->company, $this->period, $this->cash, $this->income, 'Visible enero', '2026-01-15', '100.00');
        $other = $this->createCompany('Empresa Confidencial');
        $otherPeriod = $this->createPeriod($other);
        $otherCash = $this->createAccount($other, '1.1.01', 'Caja secreta', 'asset', 'debit');
        $otherIncome = $this->createAccount($other, '4.1.01', 'Ventas secretas', 'income', 'credit');
        $this->createEntry($other, $otherPeriod, $otherCash, $otherIncome, 'Dato secreto', '2026-01-15', '900.00');

        foreach (['general-ledger', 'trial-balance', 'income-statement'] as $report) {
            $values = $this->flatten($this->xlsxRows($this->get(route('accounting.'.$report.'.export.excel', [
                'date_from' => '2026-01-01', 'date_to' => '2026-01-31', 'company_id' => $other->id,
            ]))));
            $this->assertNotContains('Empresa Confidencial', $values);
            $this->assertNotContains('Dato secreto', $values);
            $this->assertNotContains(900.0, $values);
        }
    }

    public function test_trial_balance_totals_and_balance_sheet_equation_are_preserved(): void
    {
        $this->createEntry($this->company, $this->period, $this->cash, $this->income, 'Venta cuadrada', '2026-01-15', '125.50');
        $trialRows = $this->xlsxRows($this->get(route('accounting.trial-balance.export.excel')));
        $trialTotals = collect($trialRows)->first(fn (array $row) => ($row[1] ?? null) === 'Totales generales');
        $this->assertEquals($trialTotals[4], $trialTotals[5]);
        $this->assertEquals($trialTotals[6], $trialTotals[7]);

        $balanceRows = $this->xlsxRows($this->get(route('accounting.balance-sheet.export.excel')));
        $values = collect($balanceRows)->keyBy(fn (array $row) => $row[1] ?? uniqid());
        $this->assertEquals($values['Total Activos'][2], $values['Total pasivos + patrimonio'][2]);
        $this->assertEquals(0.0, $values['Diferencia (cuadrado)'][2]);
    }

    public function test_empty_reports_export_without_exception(): void
    {
        foreach (['general-ledger', 'trial-balance', 'income-statement', 'balance-sheet'] as $report) {
            $this->get(route('accounting.'.$report.'.export.pdf'))->assertOk();
            $values = $this->flatten($this->xlsxRows($this->get(route('accounting.'.$report.'.export.excel'))));
            $this->assertContains('No existen datos para los filtros seleccionados.', $values);
        }
    }

    public function test_export_routes_require_authentication(): void
    {
        auth()->logout();
        foreach (['general-ledger', 'trial-balance', 'income-statement', 'balance-sheet'] as $report) {
            $this->get(route('accounting.'.$report.'.export.pdf'))->assertRedirect(route('login'));
            $this->get(route('accounting.'.$report.'.export.excel'))->assertRedirect(route('login'));
        }
    }

    public function test_user_without_active_company_access_cannot_export(): void
    {
        $outsider = User::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach (['general-ledger', 'trial-balance', 'income-statement', 'balance-sheet'] as $report) {
            $this->actingAs($outsider)
                ->withSession(['company_id' => $this->company->id])
                ->get(route('accounting.'.$report.'.export.pdf'))
                ->assertForbidden();
        }
    }

    public function test_period_from_another_company_is_rejected(): void
    {
        $foreignPeriod = $this->createPeriod($this->createCompany('Empresa Dos'));

        foreach (['general-ledger', 'trial-balance', 'income-statement', 'balance-sheet'] as $report) {
            $this->actingAs($this->user)
                ->withSession(['company_id' => $this->company->id])
                ->get(route('accounting.'.$report.'.export.excel', ['accounting_period_id' => $foreignPeriod->id]))
                ->assertSessionHasErrors('accounting_period_id');
        }
    }

    private function xlsxRows(TestResponse $response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = new Reader;
        $reader->open($path);
        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();
        @unlink($path);

        return $rows;
    }

    private function flatten(array $rows): array
    {
        return array_values(array_filter(array_merge(...$rows), fn ($value) => is_string($value) || is_numeric($value)));
    }

    private function createCompany(string $name): Company
    {
        return Company::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'legal_name' => $name.' S.A.', 'tax_id' => fake()->unique()->numerify('#########'), 'currency' => 'GTQ', 'timezone' => 'America/Guatemala', 'status' => 'ACTIVE']);
    }

    private function createPeriod(Company $company): AccountingPeriod
    {
        return AccountingPeriod::create(['company_id' => $company->id, 'name' => 'Enero 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'status' => AccountingPeriodStatus::OPEN->value]);
    }

    private function createAccount(Company $company, string $code, string $name, string $type, string $nature): Account
    {
        return Account::create(['company_id' => $company->id, 'code' => $code, 'name' => $name, 'account_type' => $type, 'nature' => $nature, 'allows_entries' => true, 'level' => 1, 'is_active' => true]);
    }

    private function createEntry(Company $company, AccountingPeriod $period, Account $debit, Account $credit, string $description, string $date, string $amount): JournalEntry
    {
        $entry = JournalEntry::create(['company_id' => $company->id, 'accounting_period_id' => $period->id, 'number' => 1, 'entry_date' => $date, 'description' => $description, 'status' => JournalEntryStatus::POSTED->value, 'created_by' => $this->user->id, 'posted_by' => $this->user->id, 'posted_at' => now()]);
        $entry->lines()->createMany([
            ['account_id' => $debit->id, 'description' => 'Cargo', 'debit' => $amount, 'credit' => '0.00', 'line_order' => 1],
            ['account_id' => $credit->id, 'description' => 'Abono', 'debit' => '0.00', 'credit' => $amount, 'line_order' => 2],
        ]);

        return $entry;
    }
}
