<?php

namespace Tests\Feature\Fiscal;

use App\Enums\AccountingPeriodStatus;
use App\Enums\CompanyStatus;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\JournalEntry;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fiscal\FiscalBooksService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class FiscalBooksTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Company $company;

    private Company $otherCompany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Tenant fiscal', 'email' => 'fiscal@example.test', 'status' => 'ACTIVE']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->company = $this->company('Empresa fiscal', '1001001');
        $this->otherCompany = $this->company('Empresa ajena', '2002002');
        $this->attach($this->company);
        $this->attach($this->otherCompany);
    }

    public function test_purchase_and_sale_are_stored_with_forced_direction(): void
    {
        $this->asCompany($this->company)->post(route('fiscal-purchases.store'), $this->payload([
            'third_party_name' => 'Proveedor Uno',
            'direction' => 'SALE',
        ]))->assertRedirect();

        $this->post(route('fiscal-sales.store'), $this->payload([
            'third_party_name' => 'Cliente Uno',
            'direction' => 'PURCHASE',
            'grants_tax_credit' => true,
        ]))->assertRedirect();

        $this->assertDatabaseHas('fiscal_documents', ['third_party_name' => 'Proveedor Uno', 'direction' => 'PURCHASE']);
        $this->assertDatabaseHas('fiscal_documents', ['third_party_name' => 'Cliente Uno', 'direction' => 'SALE', 'grants_tax_credit' => false]);
    }

    public function test_company_cannot_list_view_edit_or_void_another_company_documents(): void
    {
        $own = $this->document($this->company, FiscalDocumentDirection::PURCHASE, ['third_party_name' => 'Documento propio']);
        $other = $this->document($this->otherCompany, FiscalDocumentDirection::PURCHASE, ['third_party_name' => 'Documento secreto']);

        $this->asCompany($this->company)->get(route('fiscal-purchases.index'))
            ->assertOk()->assertSee($own->third_party_name)->assertDontSee($other->third_party_name);
        $this->get(route('fiscal-purchases.show', $other->id))->assertNotFound();
        $this->get(route('fiscal-purchases.edit', $other->id))->assertNotFound();
        $this->post(route('fiscal-purchases.void', $other->id))->assertNotFound();
        $this->assertSame(FiscalDocumentStatus::ACTIVE, $other->fresh()->status);
    }

    public function test_purchase_book_only_shows_purchases_and_sales_book_only_sales(): void
    {
        $this->document($this->company, FiscalDocumentDirection::PURCHASE, ['third_party_name' => 'Solo compra']);
        $this->document($this->company, FiscalDocumentDirection::SALE, ['third_party_name' => 'Solo venta']);

        $this->asCompany($this->company)->get(route('fiscal-purchases.index'))->assertSee('Solo compra')->assertDontSee('Solo venta');
        $this->get(route('fiscal-sales.index'))->assertSee('Solo venta')->assertDontSee('Solo compra');
    }

    public function test_uuid_is_unique_per_company_but_can_repeat_in_another_company(): void
    {
        $uuid = 'AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE';
        $this->asCompany($this->company)->post(route('fiscal-purchases.store'), $this->payload(['authorization_uuid' => $uuid]))->assertRedirect();
        $this->post(route('fiscal-purchases.store'), $this->payload(['authorization_uuid' => $uuid]))->assertSessionHasErrors('authorization_uuid');

        $this->asCompany($this->otherCompany)->post(route('fiscal-purchases.store'), $this->payload(['authorization_uuid' => $uuid]))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, FiscalDocument::where('authorization_uuid', $uuid)->count());
    }

    public function test_voided_documents_are_visible_but_excluded_from_totals(): void
    {
        $active = $this->document($this->company, FiscalDocumentDirection::PURCHASE, ['total_amount' => '112.00']);
        $voided = $this->document($this->company, FiscalDocumentDirection::PURCHASE, ['total_amount' => '224.00']);
        $this->asCompany($this->company)->post(route('fiscal-purchases.void', $voided->id), ['void_reason' => 'Duplicado'])->assertRedirect();

        $report = app(FiscalBooksService::class)->build($this->company->id, FiscalDocumentDirection::PURCHASE, []);
        $this->assertSame('112.00', $report['totals']['total_amount']);
        $this->assertSame(1, $report['count']);
        $this->assertSame(FiscalDocumentStatus::VOIDED, $voided->fresh()->status);
        $this->assertNotNull($voided->fresh()->voided_by);
        $this->assertDatabaseHas('fiscal_documents', ['id' => $active->id]);
    }

    public function test_credit_notes_subtract_and_debit_notes_add_without_negative_storage(): void
    {
        $this->document($this->company, FiscalDocumentDirection::SALE, ['total_amount' => '112.00']);
        $credit = $this->document($this->company, FiscalDocumentDirection::SALE, ['document_type' => FiscalDocumentType::CREDIT_NOTE, 'taxable_amount' => '20.00', 'vat_amount' => '2.40', 'total_amount' => '22.40']);
        $this->document($this->company, FiscalDocumentDirection::SALE, ['document_type' => FiscalDocumentType::DEBIT_NOTE, 'taxable_amount' => '50.00', 'vat_amount' => '6.00', 'total_amount' => '56.00']);

        $report = app(FiscalBooksService::class)->build($this->company->id, FiscalDocumentDirection::SALE, []);
        $this->assertSame('145.60', $report['totals']['total_amount']);
        $this->assertSame('22.40', $credit->fresh()->total_amount);

        $this->asCompany($this->company)->post(route('fiscal-sales.store'), $this->payload(['total_amount' => '-1.00']))
            ->assertSessionHasErrors('total_amount');
    }

    public function test_small_taxpayer_and_exempt_rules_are_enforced(): void
    {
        $this->asCompany($this->company)->post(route('fiscal-purchases.store'), $this->payload([
            'tax_category' => FiscalTaxCategory::SMALL_TAXPAYER->value,
            'is_small_taxpayer' => true,
            'vat_amount' => '12.00',
            'grants_tax_credit' => true,
        ]))->assertSessionHasErrors(['vat_amount', 'grants_tax_credit']);

        $this->post(route('fiscal-purchases.store'), $this->payload([
            'tax_category' => FiscalTaxCategory::SMALL_TAXPAYER->value,
            'is_small_taxpayer' => true,
            'taxable_amount' => '100.00',
            'vat_amount' => '0.00',
            'total_amount' => '100.00',
        ]))->assertRedirect();
        $small = FiscalDocument::where('tax_category', FiscalTaxCategory::SMALL_TAXPAYER->value)->firstOrFail();
        $this->assertSame('0.00', $small->vat_amount);
        $this->assertFalse($small->grants_tax_credit);

        $this->post(route('fiscal-sales.store'), $this->payload([
            'tax_category' => FiscalTaxCategory::EXEMPT->value,
            'taxable_amount' => '0.00', 'exempt_amount' => '100.00', 'vat_amount' => '0.00', 'total_amount' => '100.00',
        ]))->assertRedirect();
        $this->assertDatabaseHas('fiscal_documents', ['direction' => 'SALE', 'tax_category' => 'EXEMPT', 'vat_amount' => '0.00']);
    }

    public function test_total_validation_accepts_two_cents_tolerance_and_rejects_more(): void
    {
        $this->asCompany($this->company)->post(route('fiscal-purchases.store'), $this->payload(['total_amount' => '112.02']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('fiscal-purchases.store'), $this->payload(['authorization_uuid' => 'DIFFERENT', 'total_amount' => '112.03']))
            ->assertSessionHasErrors('total_amount');
    }

    public function test_closed_period_blocks_create_update_and_void(): void
    {
        $period = AccountingPeriod::create([
            'company_id' => $this->company->id, 'name' => 'Enero cerrado', 'start_date' => '2026-01-01',
            'end_date' => '2026-01-31', 'status' => AccountingPeriodStatus::CLOSED,
        ]);
        $this->asCompany($this->company)->post(route('fiscal-purchases.store'), $this->payload([
            'document_date' => '2026-01-15', 'accounting_period_id' => $period->id,
        ]))->assertSessionHasErrors('document_date');

        $document = $this->document($this->company, FiscalDocumentDirection::PURCHASE, [
            'document_date' => '2026-01-10', 'accounting_period_id' => $period->id,
        ]);
        $this->get(route('fiscal-purchases.edit', $document->id))
            ->assertRedirect(route('fiscal-purchases.show', $document->id))
            ->assertSessionHas('warning');
        $this->put(route('fiscal-purchases.update', $document->id), $this->payload([
            'document_date' => '2026-01-10', 'accounting_period_id' => $period->id,
        ]))->assertSessionHasErrors('document_date');
        $this->post(route('fiscal-purchases.void', $document->id))->assertSessionHasErrors('document_date');
        $this->assertSame(FiscalDocumentStatus::ACTIVE, $document->fresh()->status);
    }

    public function test_period_and_journal_entry_must_belong_to_active_company(): void
    {
        $period = AccountingPeriod::create([
            'company_id' => $this->otherCompany->id, 'name' => 'Período ajeno', 'start_date' => '2026-01-01',
            'end_date' => '2026-01-31', 'status' => AccountingPeriodStatus::OPEN,
        ]);

        $this->asCompany($this->company)->post(route('fiscal-purchases.store'), $this->payload(['accounting_period_id' => $period->id]))
            ->assertSessionHasErrors('accounting_period_id');

        $entry = JournalEntry::create([
            'company_id' => $this->otherCompany->id, 'accounting_period_id' => $period->id, 'number' => 1,
            'entry_date' => '2026-01-15', 'description' => 'Póliza ajena', 'status' => 'draft', 'created_by' => $this->user->id,
        ]);
        $this->post(route('fiscal-purchases.store'), $this->payload(['journal_entry_id' => $entry->id]))
            ->assertSessionHasErrors('journal_entry_id');
    }

    public function test_user_without_active_company_is_redirected_with_clear_message(): void
    {
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($user)->get(route('fiscal-purchases.index'))
            ->assertRedirect(route('companies.index'))
            ->assertSessionHas('warning', 'Primero debes seleccionar una empresa activa.');
    }

    public function test_an_active_document_can_be_updated_and_keeps_its_company(): void
    {
        $document = $this->document($this->company, FiscalDocumentDirection::PURCHASE, ['authorization_uuid' => 'UUID-EDITABLE']);

        $this->asCompany($this->company)->put(route('fiscal-purchases.update', $document->id), $this->payload([
            'authorization_uuid' => 'UUID-EDITABLE',
            'third_party_name' => 'Proveedor actualizado',
            'company_id' => $this->otherCompany->id,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('fiscal-purchases.show', $document->id));

        $this->assertSame('Proveedor actualizado', $document->fresh()->third_party_name);
        $this->assertSame($this->company->id, $document->fresh()->company_id);
    }

    public function test_date_and_third_party_filters_work(): void
    {
        $this->document($this->company, FiscalDocumentDirection::PURCHASE, ['document_date' => '2026-01-10', 'third_party_name' => 'Proveedor Enero']);
        $this->document($this->company, FiscalDocumentDirection::PURCHASE, ['document_date' => '2026-02-10', 'third_party_name' => 'Proveedor Febrero']);

        $this->asCompany($this->company)->get(route('fiscal-purchases.index', [
            'date_from' => '2026-02-01', 'date_to' => '2026-02-28', 'third_party_name' => 'Febrero',
        ]))->assertOk()->assertSee('Proveedor Febrero')->assertDontSee('Proveedor Enero');
    }

    public function test_pdf_and_excel_exports_respect_active_company_and_filters(): void
    {
        $this->document($this->company, FiscalDocumentDirection::PURCHASE, ['third_party_name' => 'Proveedor exportado']);
        $this->document($this->otherCompany, FiscalDocumentDirection::PURCHASE, ['third_party_name' => 'Proveedor ajeno']);

        $this->asCompany($this->company)->get(route('fiscal-purchases.export.pdf', ['third_party_name' => 'exportado']))
            ->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('fiscal-purchases.export.excel', ['third_party_name' => 'exportado']))
            ->assertOk()->assertDownload();
        $this->get(route('fiscal-sales.export.pdf'))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('fiscal-sales.export.excel'))->assertOk()->assertDownload();
    }

    public function test_review_filters_change_visible_documents_counters_and_totals_with_voided_priority(): void
    {
        $approvedFel = $this->felDocument('APPROVED', 'FEL-APPROVED');
        $observedFel = $this->felDocument('OBSERVED', 'FEL-OBSERVED');
        $rejectedFel = $this->felDocument('REJECTED', 'FEL-REJECTED');
        $voidedFel = $this->felDocument('APPROVED', 'FEL-VOIDED');

        $this->document($this->company, FiscalDocumentDirection::PURCHASE, [
            'fel_document_id' => $approvedFel, 'third_party_name' => 'Proveedor aprobado',
        ]);
        $this->document($this->company, FiscalDocumentDirection::PURCHASE, [
            'fel_document_id' => $observedFel, 'third_party_name' => 'Proveedor observado',
            'taxable_amount' => '200.00', 'vat_amount' => '24.00', 'total_amount' => '224.00',
        ]);
        $this->document($this->company, FiscalDocumentDirection::PURCHASE, [
            'fel_document_id' => $rejectedFel, 'third_party_name' => 'Proveedor rechazado',
            'taxable_amount' => '300.00', 'vat_amount' => '36.00', 'total_amount' => '336.00',
        ]);
        $this->document($this->company, FiscalDocumentDirection::PURCHASE, [
            'fel_document_id' => $voidedFel, 'third_party_name' => 'Proveedor anulado',
            'taxable_amount' => '400.00', 'vat_amount' => '48.00', 'total_amount' => '448.00',
            'status' => FiscalDocumentStatus::VOIDED,
        ]);

        $service = app(FiscalBooksService::class);
        $approved = $service->build($this->company->id, FiscalDocumentDirection::PURCHASE, ['review_status' => 'APPROVED']);
        $observed = $service->build($this->company->id, FiscalDocumentDirection::PURCHASE, ['review_status' => 'OBSERVED']);
        $rejected = $service->build($this->company->id, FiscalDocumentDirection::PURCHASE, ['review_status' => 'REJECTED']);
        $voided = $service->build($this->company->id, FiscalDocumentDirection::PURCHASE, ['review_status' => 'VOIDED']);

        $this->assertSame(['ALL' => 4, 'APPROVED' => 1, 'OBSERVED' => 1, 'REJECTED' => 1, 'VOIDED' => 1], $approved['statusCounts']);
        $this->assertSame('112.00', $approved['totals']['total_amount']);
        $this->assertSame('224.00', $observed['totals']['total_amount']);
        $this->assertSame('336.00', $rejected['totals']['total_amount']);
        $this->assertSame('0.00', $voided['totals']['total_amount']);
        $this->assertSame(1, $voided['shownCount']);
        $this->assertSame(0, $voided['count']);

        $this->asCompany($this->company)->get(route('fiscal-purchases.index', ['review_status' => 'OBSERVED']))
            ->assertOk()
            ->assertSee('Proveedor observado')
            ->assertDontSee('Proveedor aprobado')
            ->assertDontSee('Proveedor anulado')
            ->assertSee('Documentos mostrados:')
            ->assertSee('Q 224.00');
    }

    public function test_default_period_is_selected_and_shared_between_purchase_and_sales_books(): void
    {
        $january = AccountingPeriod::create([
            'company_id' => $this->company->id, 'name' => 'Enero 2026', 'start_date' => '2026-01-01',
            'end_date' => '2026-01-31', 'status' => AccountingPeriodStatus::CLOSED,
        ]);
        $july = AccountingPeriod::create([
            'company_id' => $this->company->id, 'name' => 'Julio 2026', 'start_date' => '2026-07-01',
            'end_date' => '2026-07-31', 'status' => AccountingPeriodStatus::OPEN,
        ]);
        $this->document($this->company, FiscalDocumentDirection::SALE, [
            'document_date' => '2026-07-20', 'accounting_period_id' => $july->id,
        ]);

        $this->asCompany($this->company)->get(route('fiscal-purchases.index'))
            ->assertOk()
            ->assertSessionHas('fiscal_books_period_id', $july->id)
            ->assertSee('Julio 2026')
            ->assertSee('01/07/2026 al 31/07/2026');

        $this->get(route('fiscal-sales.index'))
            ->assertOk()
            ->assertSessionHas('fiscal_books_period_id', $july->id)
            ->assertSee('Julio 2026');

        $this->get(route('fiscal-sales.index', ['accounting_period_id' => $january->id]))
            ->assertOk()
            ->assertSessionHas('fiscal_books_period_id', $january->id)
            ->assertSee('Enero 2026');

        $this->get(route('fiscal-purchases.index'))
            ->assertOk()
            ->assertSessionHas('fiscal_books_period_id', $january->id)
            ->assertSee('Enero 2026');
    }

    public function test_pdf_view_and_excel_include_report_metadata_and_exact_review_filter(): void
    {
        $period = AccountingPeriod::create([
            'company_id' => $this->company->id, 'name' => 'Julio 2026', 'start_date' => '2026-07-01',
            'end_date' => '2026-07-31', 'status' => AccountingPeriodStatus::OPEN,
        ]);
        $approved = $this->document($this->company, FiscalDocumentDirection::PURCHASE, [
            'fel_document_id' => $this->felDocument('APPROVED', 'EXPORT-APPROVED'),
            'accounting_period_id' => $period->id, 'third_party_name' => 'Proveedor aprobado exportación',
        ]);
        $observed = $this->document($this->company, FiscalDocumentDirection::PURCHASE, [
            'fel_document_id' => $this->felDocument('OBSERVED', 'EXPORT-OBSERVED'),
            'accounting_period_id' => $period->id, 'third_party_name' => 'Proveedor observado exportación',
            'taxable_amount' => '200.00', 'vat_amount' => '24.00', 'total_amount' => '224.00',
        ]);
        $filters = ['accounting_period_id' => $period->id, 'review_status' => 'OBSERVED'];
        $report = app(FiscalBooksService::class)->build($this->company->id, FiscalDocumentDirection::PURCHASE, $filters);
        $generatedAt = CarbonImmutable::parse('2026-07-27 15:54:21');

        $html = view('fiscal_documents.exports.pdf', [
            'company' => $this->company,
            'user' => $this->user,
            'direction' => FiscalDocumentDirection::PURCHASE,
            'report' => $report,
            'generatedAt' => $generatedAt,
        ])->render();
        $this->assertStringContainsString($this->company->legal_name, $html);
        $this->assertStringContainsString('Julio 2026', $html);
        $this->assertStringContainsString('01/07/2026 al 31/07/2026', $html);
        $this->assertStringContainsString('Observados', $html);
        $this->assertStringContainsString('27/07/2026 15:54:21', $html);
        $this->assertStringContainsString($this->user->name, $html);
        $this->assertStringContainsString($observed->third_party_name, $html);
        $this->assertStringNotContainsString($approved->third_party_name, $html);

        $rows = $this->xlsxRows($this->asCompany($this->company)->get(route('fiscal-purchases.export.excel', $filters)));
        $values = array_merge(...$rows);
        $this->assertContains($this->company->legal_name, $values);
        $this->assertContains('Libro de Compras', $values);
        $this->assertContains('Julio 2026', $values);
        $this->assertContains('01/07/2026 al 31/07/2026', $values);
        $this->assertContains('Observados', $values);
        $this->assertContains($this->user->name, $values);
        $this->assertContains($observed->third_party_name, $values);
        $this->assertNotContains($approved->third_party_name, $values);
    }

    public function test_fiscal_documents_never_create_journal_entries(): void
    {
        $before = DB::table('journal_entries')->count();
        $this->asCompany($this->company)->post(route('fiscal-purchases.store'), $this->payload())->assertRedirect();
        $this->post(route('fiscal-sales.store'), $this->payload(['authorization_uuid' => 'SALE-UUID']))->assertRedirect();

        $this->assertSame($before, DB::table('journal_entries')->count());
    }

    private function asCompany(Company $company): static
    {
        return $this->actingAs($this->user)->withSession(['company_id' => $company->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'document_date' => '2026-07-15', 'document_type' => 'INVOICE', 'tax_category' => 'GOODS',
            'series' => 'A', 'document_number' => '100', 'authorization_uuid' => fake()->uuid(),
            'third_party_tax_id' => '1234567', 'third_party_name' => 'Tercero fiscal', 'third_party_address' => 'Guatemala',
            'currency' => 'GTQ', 'exchange_rate' => '1.000000', 'taxable_amount' => '100.00', 'exempt_amount' => '0.00',
            'non_taxable_amount' => '0.00', 'vat_amount' => '12.00', 'other_taxes_amount' => '0.00', 'total_amount' => '112.00',
            'grants_tax_credit' => false, 'is_small_taxpayer' => false, 'notes' => null,
        ], $overrides);
    }

    private function document(Company $company, FiscalDocumentDirection $direction, array $overrides = []): FiscalDocument
    {
        return FiscalDocument::create(array_merge([
            'company_id' => $company->id, 'direction' => $direction, 'document_type' => FiscalDocumentType::INVOICE,
            'tax_category' => FiscalTaxCategory::GOODS, 'document_date' => '2026-07-15', 'third_party_name' => 'Tercero',
            'currency' => 'GTQ', 'exchange_rate' => '1.000000', 'taxable_amount' => '100.00', 'exempt_amount' => '0.00',
            'non_taxable_amount' => '0.00', 'vat_amount' => '12.00', 'other_taxes_amount' => '0.00', 'total_amount' => '112.00',
            'grants_tax_credit' => $direction === FiscalDocumentDirection::PURCHASE, 'is_small_taxpayer' => false,
            'status' => FiscalDocumentStatus::ACTIVE, 'created_by' => $this->user->id,
        ], $overrides));
    }

    private function felDocument(string $status, string $uuid): int
    {
        return DB::table('fel_documents')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'authorization_uuid' => $uuid,
            'dte_type' => 'FACT',
            'currency' => 'GTQ',
            'source_type' => 'XML',
            'data_level' => 'FULL_DETAIL',
            'operation_type' => 'PURCHASE',
            'classification' => 'GENERAL_PURCHASE',
            'status' => $status,
            'fiscal_status' => 'ACTIVE',
            'issued_at' => '2026-07-15 10:00:00',
            'requires_tax_review' => false,
            'requires_accounting_review' => true,
            'signature_count' => 1,
            'imported_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function xlsxRows(TestResponse $response): array
    {
        $response->assertOk()->assertDownload();
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

    private function company(string $name, string $taxId): Company
    {
        return Company::create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'legal_name' => $name.' S.A.',
            'tax_id' => $taxId, 'status' => CompanyStatus::ACTIVE,
        ]);
    }

    private function attach(Company $company): void
    {
        $this->user->companies()->attach($company, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);
    }
}
