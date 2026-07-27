<?php

namespace Tests\Feature\Fiscal;

use App\Enums\CompanyStatus;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentSource;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FelDocument;
use App\Models\FiscalDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fiscal\FelFiscalDocumentSyncService;
use App\Services\Fiscal\FiscalBooksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FelFiscalSyncTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->tenant = Tenant::create(['name' => 'Tenant sync', 'email' => 'sync@example.test', 'status' => 'ACTIVE']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->company = $this->company('Empresa receptora', '7654321');
        $this->attach($this->user, $this->company);
    }

    public function test_received_xml_automatically_creates_one_purchase_without_journal_entry(): void
    {
        $this->importXml();

        $fel = FelDocument::firstOrFail();
        $fiscal = FiscalDocument::firstOrFail();
        $this->assertSame($fel->id, $fiscal->fel_document_id);
        $this->assertSame(FiscalDocumentDirection::PURCHASE, $fiscal->direction);
        $this->assertSame(FiscalDocumentType::INVOICE, $fiscal->document_type);
        $this->assertSame(FiscalDocumentSource::FEL, $fiscal->source);
        $this->assertSame('1234567', $fiscal->third_party_tax_id);
        $this->assertNull($fiscal->journal_entry_id);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_issued_xml_automatically_creates_one_sale(): void
    {
        $company = $this->company('Empresa emisora', '1234567');
        $this->attach($this->user, $company);
        $this->importXml($company);

        $fiscal = FiscalDocument::firstOrFail();
        $this->assertSame(FiscalDocumentDirection::SALE, $fiscal->direction);
        $this->assertSame('7654321', $fiscal->third_party_tax_id);
        $this->assertFalse($fiscal->grants_tax_credit);
    }

    public function test_unknown_operation_is_observed_without_creating_fiscal_document(): void
    {
        $fel = $this->fel(operation: 'UNKNOWN');

        $result = app(FelFiscalDocumentSyncService::class)->syncFromFel($fel, $this->user);

        $this->assertTrue($result->observed());
        $this->assertDatabaseCount('fiscal_documents', 0);
        $this->assertNotEmpty(data_get($fel->fresh()->metadata, 'fiscal_sync.warnings'));
    }

    public function test_dte_category_and_type_rules_are_applied_without_positive_credit_assumption(): void
    {
        $small = $this->fel(index: 1, dte: 'FPEQ', regime: 'PEQ', taxable: '0', vat: '0', total: '100');
        $fuel = $this->fel(index: 2, dte: 'FACT', classification: 'FUEL');
        $lodging = $this->fel(index: 3, dte: 'FACT', classification: 'LODGING');
        $unknownType = $this->fel(index: 4, dte: 'NUEVO');

        foreach ([$small, $fuel, $lodging, $unknownType] as $document) {
            app(FelFiscalDocumentSyncService::class)->syncFromFel($document, $this->user);
        }

        $this->assertSame(FiscalTaxCategory::SMALL_TAXPAYER, $small->fiscalDocument->tax_category);
        $this->assertTrue($small->fiscalDocument->is_small_taxpayer);
        $this->assertFalse($small->fiscalDocument->grants_tax_credit);
        $this->assertSame(FiscalTaxCategory::FUEL, $fuel->fiscalDocument->tax_category);
        $this->assertSame(FiscalTaxCategory::SERVICES, $lodging->fiscalDocument->tax_category);
        $this->assertSame(FiscalDocumentType::OTHER, $unknownType->fiscalDocument->document_type);
        $this->assertNotEmpty($unknownType->fiscalDocument->source_metadata['warnings']);
    }

    public function test_positive_vat_on_small_taxpayer_is_a_controlled_conflict(): void
    {
        $fel = $this->fel(dte: 'FPEQ', regime: 'PEQ');

        $result = app(FelFiscalDocumentSyncService::class)->syncFromFel($fel, $this->user);

        $this->assertTrue($result->conflict());
        $this->assertDatabaseCount('fiscal_documents', 0);
    }

    public function test_manual_document_with_same_uuid_is_not_overwritten(): void
    {
        $fel = $this->fel();
        $manual = FiscalDocument::factory()->create([
            'company_id' => $this->company->id,
            'authorization_uuid' => $fel->authorization_uuid,
            'source' => FiscalDocumentSource::MANUAL,
        ]);

        $result = app(FelFiscalDocumentSyncService::class)->syncFromFel($fel, $this->user);

        $this->assertTrue($result->conflict());
        $this->assertNull($manual->fresh()->fel_document_id);
        $this->assertDatabaseCount('fiscal_documents', 1);
    }

    public function test_closed_period_and_foreign_currency_are_observed(): void
    {
        AccountingPeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Julio cerrado',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => 'closed',
        ]);
        $closed = app(FelFiscalDocumentSyncService::class)->syncFromFel($this->fel(), $this->user);
        $foreignFel = $this->fel(index: 2);
        $foreignFel->update(['currency' => 'USD']);
        $foreign = app(FelFiscalDocumentSyncService::class)->syncFromFel($foreignFel->refresh(), $this->user);

        $this->assertTrue($closed->observed());
        $this->assertTrue($foreign->observed());
        $this->assertDatabaseCount('fiscal_documents', 0);
    }

    public function test_open_period_is_assigned_only_from_same_company(): void
    {
        $other = $this->company('Otra empresa', '9999999');
        AccountingPeriod::create(['company_id' => $other->id, 'name' => 'Ajeno', 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'status' => 'open']);
        $own = AccountingPeriod::create(['company_id' => $this->company->id, 'name' => 'Propio', 'start_date' => '2026-07-01', 'end_date' => '2026-07-31', 'status' => 'open']);

        app(FelFiscalDocumentSyncService::class)->syncFromFel($this->fel(), $this->user);

        $this->assertSame($own->id, FiscalDocument::firstOrFail()->accounting_period_id);
    }

    public function test_duplicate_import_is_idempotent_and_can_recreate_a_missing_counterpart(): void
    {
        $this->importXml();
        FiscalDocument::firstOrFail()->forceDelete();

        $this->importXml();

        $this->assertDatabaseCount('fel_documents', 1);
        $this->assertDatabaseCount('fiscal_documents', 1);
        $this->assertDatabaseHas('fel_import_rows', ['status' => 'DUPLICATE']);
    }

    public function test_uuid_format_variants_do_not_duplicate_fel_or_fiscal_documents(): void
    {
        $this->importXml();
        $xml = str_replace(
            '11111111-2222-4333-8444-555555555555',
            ' {11111111222243338444555555555555} ',
            file_get_contents(base_path('tests/Fixtures/fel/documento-valido.xml')),
        );
        $variant = UploadedFile::fake()->createWithContent('variante.xml', $xml);

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post(route('fel-imports.store'), ['files' => [$variant]])
            ->assertRedirect();

        $this->assertDatabaseCount('fel_documents', 1);
        $this->assertDatabaseCount('fiscal_documents', 1);
    }

    public function test_item_iva_base_avoids_duplicating_other_tax_bases(): void
    {
        $fel = $this->fel(classification: 'LODGING', taxable: '200', vat: '12', total: '117');
        $fel->taxes()->create([
            'tax_name' => 'IVA', 'tax_name_normalized' => 'IVA', 'taxable_amount' => '100',
            'tax_amount' => '12', 'source_level' => 'ITEM',
        ]);
        $fel->taxes()->create([
            'tax_name' => 'TURISMO HOSPEDAJE', 'tax_name_normalized' => 'TURISMO HOSPEDAJE',
            'taxable_amount' => '100', 'tax_amount' => '5', 'source_level' => 'ITEM',
        ]);
        $fel->update(['other_tax_total' => '5']);

        $result = app(FelFiscalDocumentSyncService::class)->syncFromFel($fel->refresh(), $this->user);

        $this->assertTrue($result->created());
        $this->assertSame('100.00', $fel->fresh()->fiscalDocument->taxable_amount);
        $this->assertContains('taxable_amount', $fel->fiscalDocument->source_metadata['values_calculated']);
    }

    public function test_reclassification_direction_change_updates_same_fiscal_document(): void
    {
        $fel = $this->fel();
        app(FelFiscalDocumentSyncService::class)->syncFromFel($fel, $this->user);
        $fiscalId = $fel->fiscalDocument->id;
        $fel->update(['operation_type' => 'SALE']);

        $result = app(FelFiscalDocumentSyncService::class)->syncFromFel($fel->refresh(), $this->user);

        $this->assertTrue($result->updated());
        $this->assertSame($fiscalId, $fel->fresh()->fiscalDocument->id);
        $this->assertSame(FiscalDocumentDirection::SALE, $fel->fresh()->fiscalDocument->direction);
        $this->assertDatabaseCount('fiscal_documents', 1);
    }

    public function test_cancellation_xml_voids_existing_fel_and_fiscal_document(): void
    {
        $this->importXml();

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post(route('fel-imports.store'), ['files' => [$this->upload('anulacion-valida.xml')]])
            ->assertRedirect();

        $this->assertSame('VOIDED', FelDocument::firstOrFail()->fiscal_status->value);
        $this->assertSame(FiscalDocumentStatus::VOIDED, FiscalDocument::firstOrFail()->status);
        $this->assertNotNull(FiscalDocument::firstOrFail()->voided_at);
        $this->assertSame(0, app(FiscalBooksService::class)->build($this->company->id, FiscalDocumentDirection::PURCHASE, [])['count']);
    }

    public function test_cancellation_before_original_is_observed_without_incomplete_documents(): void
    {
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post(route('fel-imports.store'), ['files' => [$this->upload('anulacion-valida.xml')]])
            ->assertRedirect();

        $this->assertDatabaseCount('fel_documents', 0);
        $this->assertDatabaseCount('fiscal_documents', 0);
        $this->assertDatabaseHas('fel_import_rows', ['status' => 'OBSERVED', 'processing_stage' => 'xml_cancellation']);
    }

    public function test_rejected_human_state_does_not_void_fiscal_document(): void
    {
        $fel = $this->fel();
        $fel->update(['status' => 'REJECTED']);
        app(FelFiscalDocumentSyncService::class)->syncFromFel($fel->refresh(), $this->user);

        $this->assertSame(FiscalDocumentStatus::ACTIVE, FiscalDocument::firstOrFail()->status);
    }

    public function test_backfill_dry_run_and_repeated_execution_are_idempotent(): void
    {
        $this->fel();
        $this->artisan('fiscal:sync-fel', ['--company' => $this->company->id, '--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();
        $this->assertDatabaseCount('fiscal_documents', 0);

        $this->artisan('fiscal:sync-fel', ['--company' => $this->company->id, '--no-interaction' => true])->assertSuccessful();
        $this->artisan('fiscal:sync-fel', ['--company' => $this->company->id, '--no-interaction' => true])->assertSuccessful();
        $this->assertDatabaseCount('fiscal_documents', 1);
        $this->assertNull(FiscalDocument::firstOrFail()->journal_entry_id);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_diagnostic_reports_unsynchronized_fel_without_writing(): void
    {
        $this->fel(operation: 'UNKNOWN');

        $this->artisan('fiscal:diagnose-fel-sync', ['--company' => $this->company->id])
            ->expectsOutputToContain('FEL sin documento fiscal')
            ->assertSuccessful();

        $this->assertDatabaseCount('fiscal_documents', 0);
    }

    public function test_other_tenant_and_inactive_pivot_cannot_use_the_import_flow(): void
    {
        $inactive = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $inactive->companies()->attach($this->company, ['is_owner' => false, 'is_active' => false, 'joined_at' => now()]);
        $this->actingAs($inactive)->withSession(['company_id' => $this->company->id])
            ->post(route('fel-imports.store'), ['files' => [$this->upload('documento-valido.xml')]])
            ->assertForbidden();

        $otherTenant = Tenant::create(['name' => 'Tenant ajeno', 'email' => 'other@example.test', 'status' => 'ACTIVE']);
        $outsider = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $outsider->companies()->attach($this->company, ['is_owner' => false, 'is_active' => true, 'joined_at' => now()]);
        $this->actingAs($outsider)->withSession(['company_id' => $this->company->id])
            ->post(route('fel-imports.store'), ['files' => [$this->upload('documento-valido.xml')]])
            ->assertForbidden();

        $this->assertDatabaseCount('fel_documents', 0);
        $this->assertDatabaseCount('fiscal_documents', 0);
    }

    private function importXml(?Company $company = null): void
    {
        $company ??= $this->company;
        $this->actingAs($this->user)->withSession(['company_id' => $company->id])
            ->post(route('fel-imports.store'), ['files' => [$this->upload('documento-valido.xml')]])
            ->assertRedirect();
    }

    private function upload(string $name): UploadedFile
    {
        return new UploadedFile(base_path("tests/Fixtures/fel/{$name}"), $name, 'application/xml', null, true);
    }

    private function fel(
        int $index = 1,
        string $operation = 'PURCHASE',
        string $dte = 'FACT',
        string $classification = 'GENERAL_PURCHASE',
        string $regime = 'GEN',
        string $taxable = '100',
        string $vat = '12',
        string $total = '112',
    ): FelDocument {
        return FelDocument::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'authorization_uuid' => sprintf('AAAAAAAA-BBBB-4CCC-8DDD-%012d', $index),
            'dte_type' => $dte,
            'currency' => 'GTQ',
            'source_type' => 'XML',
            'data_level' => 'FULL_DETAIL',
            'operation_type' => $operation,
            'classification' => $classification,
            'status' => 'PENDING',
            'fiscal_status' => 'ACTIVE',
            'issued_at' => '2026-07-20 10:00:00',
            'issuer_tax_id' => '1234567',
            'issuer_name' => 'Emisor de prueba',
            'issuer_tax_regime' => $regime,
            'receiver_tax_id' => '7654321',
            'receiver_name' => 'Receptor de prueba',
            'taxable_total' => $taxable,
            'tax_total' => $vat,
            'other_tax_total' => '0',
            'grand_total' => $total,
            'imported_by' => $this->user->id,
        ]);
    }

    private function company(string $name, string $taxId): Company
    {
        return Company::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'legal_name' => $name,
            'tax_id' => $taxId,
            'status' => CompanyStatus::ACTIVE,
        ]);
    }

    private function attach(User $user, Company $company): void
    {
        $user->companies()->attach($company, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);
    }
}
