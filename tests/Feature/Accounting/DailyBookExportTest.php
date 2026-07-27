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
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class DailyBookExportTest extends TestCase
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
            'name' => 'Tenant de exportaciones',
            'email' => 'exportaciones@example.com',
            'status' => 'ACTIVE',
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->company = $this->createCompany('Compañía Ágil');
        $this->company->users()->attach($this->user->id, [
            'is_owner' => true,
            'is_active' => true,
            'joined_at' => now(),
        ]);
        $this->period = $this->createPeriod($this->company);
        $this->debitAccount = $this->createAccount($this->company, '1.1.01', 'Caja');
        $this->creditAccount = $this->createAccount($this->company, '4.1.01', 'Ventas');

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    public function test_authenticated_user_can_export_pdf(): void
    {
        $this->createEntry($this->company, $this->period, $this->debitAccount, $this->creditAccount, 1, 'Póliza con acentos', '2026-01-15', '125.50');

        $response = $this->get(route('accounting.daily-book.export.pdf'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('libro-diario-compania-agil-', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertGreaterThan(500, strlen($response->getContent()));
    }

    public function test_authenticated_user_can_export_excel_with_real_dates_and_numeric_amounts(): void
    {
        $this->createEntry($this->company, $this->period, $this->debitAccount, $this->creditAccount, 1, 'Venta exportada', '2026-01-15', '125.50');

        $response = $this->get(route('accounting.daily-book.export.excel'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('libro-diario-compania-agil-', $response->headers->get('content-disposition'));

        $rows = $this->xlsxRows($response);
        $headerIndex = $this->rowIndex($rows, 'Número de póliza');

        $this->assertSame('Libro Diario', $rows[2][0]);
        $this->assertSame('Número de póliza', $rows[$headerIndex][0]);
        $this->assertInstanceOf(DateTimeInterface::class, $rows[$headerIndex + 1][1]);
        $this->assertIsFloat($rows[$headerIndex + 1][5]);
        $this->assertSame(125.5, $rows[$headerIndex + 1][5]);
    }

    public function test_export_preserves_date_filters_and_excludes_other_company_movements(): void
    {
        $this->createEntry($this->company, $this->period, $this->debitAccount, $this->creditAccount, 1, 'Dentro del rango', '2026-01-15', '100.00');
        $this->createEntry($this->company, $this->period, $this->debitAccount, $this->creditAccount, 2, 'Fuera del rango', '2026-02-15', '200.00');

        $otherCompany = $this->createCompany('Empresa Ajena');
        $otherPeriod = $this->createPeriod($otherCompany);
        $otherDebit = $this->createAccount($otherCompany, '1.1.01', 'Caja ajena');
        $otherCredit = $this->createAccount($otherCompany, '4.1.01', 'Venta ajena');
        $this->createEntry($otherCompany, $otherPeriod, $otherDebit, $otherCredit, 1, 'Movimiento confidencial', '2026-01-15', '900.00');

        $response = $this->get(route('accounting.daily-book.export.excel', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-01-31',
            'company_id' => $otherCompany->id,
        ]));
        $values = $this->flattenRows($this->xlsxRows($response));

        $this->assertContains('Rango: 2026-01-01 al 2026-01-31', $values);
        $this->assertContains('Dentro del rango', $values);
        $this->assertNotContains('Fuera del rango', $values);
        $this->assertNotContains('Movimiento confidencial', $values);
        $this->assertNotContains('Empresa Ajena', $values);
    }

    public function test_user_without_company_access_cannot_export(): void
    {
        $outsider = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($outsider)
            ->withSession(['company_id' => $this->company->id])
            ->get(route('accounting.daily-book.export.pdf'))
            ->assertForbidden();
    }

    public function test_period_from_another_company_is_rejected_on_export(): void
    {
        $otherPeriod = $this->createPeriod($this->createCompany('Empresa Dos'));

        $this->get(route('accounting.daily-book.export.excel', [
            'accounting_period_id' => $otherPeriod->id,
        ]))->assertSessionHasErrors('accounting_period_id');
    }

    public function test_empty_report_can_be_exported_in_both_formats(): void
    {
        $pdf = $this->get(route('accounting.daily-book.export.pdf'));
        $excel = $this->get(route('accounting.daily-book.export.excel'));

        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $excel->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $rows = $this->xlsxRows($excel);
        $headerIndex = $this->rowIndex($rows, 'Número de póliza');
        $totalsIndex = $this->rowIndex($rows, 'Totales generales', 4);

        $this->assertSame('Número de póliza', $rows[$headerIndex][0]);
        $this->assertSame('Totales generales', $rows[$totalsIndex][4]);
        $this->assertEquals(0.0, $rows[$totalsIndex][5]);
        $this->assertEquals(0.0, $rows[$totalsIndex][6]);
    }

    public function test_exported_totals_match_exported_movements(): void
    {
        $this->createEntry($this->company, $this->period, $this->debitAccount, $this->creditAccount, 1, 'Primera', '2026-01-10', '100.25');
        $this->createEntry($this->company, $this->period, $this->debitAccount, $this->creditAccount, 2, 'Segunda', '2026-01-20', '25.75');

        $rows = $this->xlsxRows($this->get(route('accounting.daily-book.export.excel')));
        $headerIndex = $this->rowIndex($rows, 'Número de póliza');
        $totalsIndex = $this->rowIndex($rows, 'Totales generales', 4);
        $dataRows = array_slice($rows, $headerIndex + 1, $totalsIndex - $headerIndex - 1);
        $totals = $rows[$totalsIndex];

        $this->assertSame(126.0, array_sum(array_column($dataRows, 5)));
        $this->assertSame(126.0, array_sum(array_column($dataRows, 6)));
        $this->assertEquals(126.0, $totals[5]);
        $this->assertEquals(126.0, $totals[6]);
    }

    public function test_export_endpoints_require_authentication(): void
    {
        auth()->logout();

        $this->get(route('accounting.daily-book.export.pdf'))->assertRedirect(route('login'));
        $this->get(route('accounting.daily-book.export.excel'))->assertRedirect(route('login'));
    }

    private function xlsxRows(TestResponse $response): array
    {
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = new Reader;
        $reader->open($path);
        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $this->assertSame('Libro Diario', $sheet->getName());

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();
        @unlink($path);

        return $rows;
    }

    private function flattenRows(array $rows): array
    {
        return array_values(array_filter(
            array_merge(...$rows),
            fn ($value) => is_string($value) || is_numeric($value)
        ));
    }

    private function rowIndex(array $rows, string $value, int $column = 0): int
    {
        foreach ($rows as $index => $row) {
            if (($row[$column] ?? null) === $value) {
                return $index;
            }
        }

        $this->fail("No se encontró '{$value}' en la columna {$column} del archivo Excel.");
    }

    private function createCompany(string $name): Company
    {
        return Company::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'legal_name' => $name.' Sociedad Anónima',
            'tax_id' => fake()->unique()->numerify('#########'),
            'currency' => 'GTQ',
            'timezone' => 'America/Guatemala',
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
        Company $company,
        AccountingPeriod $period,
        Account $debitAccount,
        Account $creditAccount,
        int $number,
        string $description,
        string $date,
        string $amount,
    ): JournalEntry {
        $entry = JournalEntry::create([
            'company_id' => $company->id,
            'accounting_period_id' => $period->id,
            'number' => $number,
            'entry_date' => $date,
            'description' => $description,
            'status' => JournalEntryStatus::POSTED->value,
            'created_by' => $this->user->id,
            'posted_by' => $this->user->id,
            'posted_at' => now(),
        ]);

        $entry->lines()->createMany([
            [
                'account_id' => $debitAccount->id,
                'description' => 'Cargo',
                'debit' => $amount,
                'credit' => '0.00',
                'line_order' => 1,
            ],
            [
                'account_id' => $creditAccount->id,
                'description' => 'Abono',
                'debit' => '0.00',
                'credit' => $amount,
                'line_order' => 2,
            ],
        ]);

        return $entry;
    }
}
