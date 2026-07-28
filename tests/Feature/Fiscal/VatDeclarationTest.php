<?php

namespace Tests\Feature\Fiscal;

use App\Enums\AccountingPeriodStatus;
use App\Enums\CompanyStatus;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use App\Enums\VatDeclarationStatus;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VatDeclaration;
use App\Services\Fiscal\VatDeclarationCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class VatDeclarationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Company $company;

    private AccountingPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Tenant IVA', 'email' => 'iva@example.test', 'status' => 'ACTIVE']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Miguel IVA']);
        $this->company = $this->company('Empresa IVA', '1234567');
        $this->user->companies()->attach($this->company, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);
        $this->period = AccountingPeriod::create([
            'company_id' => $this->company->id,
            'name' => 'Julio 2026',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'status' => AccountingPeriodStatus::OPEN,
        ]);
    }

    public function test_calculation_uses_only_active_approved_documents_and_applies_notes_credit_and_balances(): void
    {
        $this->document(FiscalDocumentDirection::SALE, ['vat_amount' => '120.00', 'taxable_amount' => '1000.00', 'total_amount' => '1120.00']);
        $this->document(FiscalDocumentDirection::SALE, ['document_type' => FiscalDocumentType::CREDIT_NOTE, 'vat_amount' => '12.00', 'taxable_amount' => '100.00', 'total_amount' => '112.00']);
        $this->document(FiscalDocumentDirection::SALE, ['document_type' => FiscalDocumentType::DEBIT_NOTE, 'vat_amount' => '6.00', 'taxable_amount' => '50.00', 'total_amount' => '56.00']);
        $this->document(FiscalDocumentDirection::PURCHASE, ['vat_amount' => '60.00', 'taxable_amount' => '500.00', 'total_amount' => '560.00', 'grants_tax_credit' => true]);
        $this->document(FiscalDocumentDirection::PURCHASE, ['document_type' => FiscalDocumentType::CREDIT_NOTE, 'vat_amount' => '12.00', 'taxable_amount' => '100.00', 'total_amount' => '112.00', 'grants_tax_credit' => true]);
        $this->document(FiscalDocumentDirection::PURCHASE, ['vat_amount' => '24.00', 'taxable_amount' => '200.00', 'total_amount' => '224.00', 'grants_tax_credit' => false]);

        foreach (['OBSERVED', 'REJECTED'] as $status) {
            $this->document(FiscalDocumentDirection::SALE, [
                'fel_document_id' => $this->fel($status, 'FEL-'.$status),
                'vat_amount' => '999.00', 'taxable_amount' => '999.00', 'total_amount' => '1998.00',
            ]);
        }
        $this->document(FiscalDocumentDirection::SALE, [
            'status' => FiscalDocumentStatus::VOIDED,
            'vat_amount' => '999.00', 'taxable_amount' => '999.00', 'total_amount' => '1998.00',
        ]);

        $calculation = app(VatDeclarationCalculationService::class)->calculate($this->company->id, $this->period, [
            'previous_credit_balance' => '10.00',
            'vat_withholdings' => '5.00',
            'vat_perceptions' => '3.00',
            'manual_debit_adjustments' => '1.00',
            'manual_credit_adjustments' => '2.00',
        ]);

        $this->assertSame(6, $calculation['counters']['included']);
        $this->assertSame(1, $calculation['counters']['observed']);
        $this->assertSame(1, $calculation['counters']['rejected']);
        $this->assertSame(1, $calculation['counters']['voided']);
        $this->assertSame('114.00', $calculation['totals']['sales_vat_amount']);
        $this->assertSame('72.00', $calculation['totals']['purchase_vat_amount']);
        $this->assertSame('48.00', $calculation['totals']['purchase_creditable_vat_amount']);
        $this->assertSame('24.00', $calculation['totals']['purchase_non_creditable_vat_amount']);
        $this->assertSame('118.00', $calculation['totals']['net_vat_debit']);
        $this->assertSame('65.00', $calculation['totals']['net_vat_credit']);
        $this->assertSame('53.00', $calculation['totals']['vat_payable']);
        $this->assertSame('0.00', $calculation['totals']['credit_balance']);
    }

    public function test_draft_snapshot_recalculates_then_ready_and_filed_states_freeze_it(): void
    {
        $first = $this->document(FiscalDocumentDirection::SALE, ['third_party_name' => 'Cliente snapshot']);
        $beforeJournalEntries = DB::table('journal_entries')->count();
        $this->asCompany()->post(route('vat-declarations.store'), $this->draftPayload(['previous_credit_balance' => '5.00']))
            ->assertRedirect();

        $declaration = VatDeclaration::firstOrFail();
        $this->assertSame(VatDeclarationStatus::DRAFT, $declaration->status);
        $this->assertDatabaseHas('vat_declaration_documents', ['vat_declaration_id' => $declaration->id, 'fiscal_document_id' => $first->id, 'included' => true]);

        $second = $this->document(FiscalDocumentDirection::PURCHASE, ['third_party_name' => 'Proveedor agregado', 'grants_tax_credit' => true]);
        $this->post(route('vat-declarations.recalculate', $declaration), $this->draftPayload(['previous_credit_balance' => '5.00']))
            ->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('vat_declaration_documents', ['vat_declaration_id' => $declaration->id, 'fiscal_document_id' => $second->id]);

        $this->post(route('vat-declarations.review', $declaration))->assertRedirect();
        $this->post(route('vat-declarations.ready', $declaration))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(VatDeclarationStatus::READY_TO_FILE, $declaration->fresh()->status);
        $snapshotCount = $declaration->documents()->count();

        $this->document(FiscalDocumentDirection::SALE, ['third_party_name' => 'Cliente posterior']);
        $this->assertSame($snapshotCount, $declaration->documents()->count());
        $this->get(route('vat-declarations.show', $declaration))->assertOk()->assertSee('Los libros fiscales cambiaron');

        $this->post(route('vat-declarations.file', $declaration), [
            'filed_at' => '2026-07-27 16:00:00',
            'sat_form_number' => 'SAT-2237',
            'sat_access_number' => 'ACCESS-1',
            'sat_payment_slip_number' => 'SAT-2000-1',
            'amount_paid' => $declaration->vat_payable,
        ])->assertRedirect();
        $this->assertSame(VatDeclarationStatus::FILED, $declaration->fresh()->status);

        $this->post(route('vat-declarations.recalculate', $declaration), $this->draftPayload())->assertSessionHasErrors('status');
        $this->assertSame($beforeJournalEntries, DB::table('journal_entries')->count());
    }

    public function test_credit_balance_is_calculated_when_credit_exceeds_debit(): void
    {
        $this->document(FiscalDocumentDirection::SALE, ['vat_amount' => '12.00', 'taxable_amount' => '100.00', 'total_amount' => '112.00']);
        $this->document(FiscalDocumentDirection::PURCHASE, ['vat_amount' => '24.00', 'taxable_amount' => '200.00', 'total_amount' => '224.00', 'grants_tax_credit' => true]);
        $calculation = app(VatDeclarationCalculationService::class)->calculate($this->company->id, $this->period, ['previous_credit_balance' => '5.00']);

        $this->assertSame('0.00', $calculation['totals']['vat_payable']);
        $this->assertSame('17.00', $calculation['totals']['credit_balance']);
    }

    public function test_exports_use_snapshot_and_show_company_period_generation_and_user(): void
    {
        $document = $this->document(FiscalDocumentDirection::SALE, ['third_party_name' => 'Cliente histórico']);
        $this->asCompany()->post(route('vat-declarations.store'), $this->draftPayload())->assertRedirect();
        $declaration = VatDeclaration::with(['accountingPeriod', 'documents.fiscalDocument'])->firstOrFail();
        $document->update(['third_party_name' => 'Cliente modificado', 'vat_amount' => '999.00', 'total_amount' => '999.00']);

        $html = view('vat_declarations.exports.pdf', [
            'declaration' => $declaration,
            'company' => $this->company,
            'user' => $this->user,
            'generatedAt' => now(),
        ])->render();
        $this->assertStringContainsString($this->company->legal_name, $html);
        $this->assertStringContainsString('Julio 2026', $html);
        $this->assertStringContainsString($this->user->name, $html);
        $this->assertStringContainsString('Generado:', $html);
        $this->assertStringContainsString('Cliente histórico', $html);
        $this->assertStringNotContainsString('Cliente modificado', $html);

        $this->get(route('vat-declarations.export.pdf', $declaration))->assertOk()->assertHeader('content-type', 'application/pdf');
        $rows = $this->xlsxRows($this->get(route('vat-declarations.export.excel', $declaration)));
        $values = array_merge(...$rows);
        $this->assertContains($this->company->legal_name, $values);
        $this->assertContains('Julio 2026', $values);
        $this->assertContains($this->user->name, $values);
        $this->assertContains('Cliente histórico', $values);
        $this->assertNotContains('Cliente modificado', $values);
    }

    public function test_other_company_and_inactive_membership_cannot_access_declaration(): void
    {
        $this->document(FiscalDocumentDirection::SALE);
        $this->asCompany()->post(route('vat-declarations.store'), $this->draftPayload());
        $declaration = VatDeclaration::firstOrFail();
        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherCompany = $this->company('Otra empresa', '7654321');
        $otherUser->companies()->attach($otherCompany, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);

        $this->actingAs($otherUser)->withSession(['company_id' => $otherCompany->id])
            ->get(route('vat-declarations.show', $declaration))->assertForbidden();

        $this->user->companies()->updateExistingPivot($this->company->id, ['is_active' => false]);
        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->get(route('vat-declarations.index'))->assertForbidden();
    }

    public function test_ready_state_is_blocked_by_approved_documents_without_period_or_valid_exchange_rate(): void
    {
        $this->document(FiscalDocumentDirection::PURCHASE, [
            'accounting_period_id' => null,
            'third_party_name' => 'Compra sin período',
            'grants_tax_credit' => true,
        ]);
        $this->document(FiscalDocumentDirection::SALE, [
            'currency' => 'USD',
            'exchange_rate' => '0.000000',
            'third_party_name' => 'Venta sin tipo de cambio',
        ]);

        $this->asCompany()->post(route('vat-declarations.store'), $this->draftPayload())->assertRedirect();
        $declaration = VatDeclaration::firstOrFail();
        $this->post(route('vat-declarations.review', $declaration))->assertRedirect();
        $this->post(route('vat-declarations.ready', $declaration))
            ->assertRedirect()
            ->assertSessionHasErrors('declaration');

        $this->assertSame(VatDeclarationStatus::REVIEWED, $declaration->fresh()->status);
        $this->assertDatabaseHas('vat_declaration_documents', [
            'vat_declaration_id' => $declaration->id,
            'included' => false,
            'exclusion_reason' => 'El documento aprobado no tiene período contable asignado.',
        ]);
        $this->assertDatabaseHas('vat_declaration_documents', [
            'vat_declaration_id' => $declaration->id,
            'included' => false,
            'exclusion_reason' => 'La moneda extranjera no tiene un tipo de cambio válido.',
        ]);
    }

    private function asCompany(): static
    {
        return $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    private function draftPayload(array $overrides = []): array
    {
        return array_merge([
            'accounting_period_id' => $this->period->id,
            'previous_credit_balance' => '0.00',
            'vat_withholdings' => '0.00',
            'vat_perceptions' => '0.00',
            'manual_debit_adjustments' => '0.00',
            'manual_credit_adjustments' => '0.00',
            'notes' => 'Preparación de prueba',
        ], $overrides);
    }

    private function document(FiscalDocumentDirection $direction, array $overrides = []): FiscalDocument
    {
        return FiscalDocument::create(array_merge([
            'company_id' => $this->company->id,
            'accounting_period_id' => $this->period->id,
            'direction' => $direction,
            'document_type' => FiscalDocumentType::INVOICE,
            'tax_category' => FiscalTaxCategory::GOODS,
            'document_date' => '2026-07-15',
            'third_party_name' => $direction === FiscalDocumentDirection::SALE ? 'Cliente' : 'Proveedor',
            'currency' => 'GTQ',
            'exchange_rate' => '1.000000',
            'taxable_amount' => '100.00',
            'exempt_amount' => '0.00',
            'non_taxable_amount' => '0.00',
            'vat_amount' => '12.00',
            'other_taxes_amount' => '0.00',
            'total_amount' => '112.00',
            'grants_tax_credit' => false,
            'is_small_taxpayer' => false,
            'status' => FiscalDocumentStatus::ACTIVE,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    private function fel(string $status, string $uuid): int
    {
        return DB::table('fel_documents')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'authorization_uuid' => $uuid,
            'dte_type' => 'FACT',
            'currency' => 'GTQ',
            'source_type' => 'XML',
            'data_level' => 'FULL_DETAIL',
            'operation_type' => 'SALE',
            'classification' => 'GENERAL_SALE',
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

    private function company(string $name, string $taxId): Company
    {
        return Company::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'legal_name' => $name.' S.A.',
            'tax_id' => $taxId,
            'status' => CompanyStatus::ACTIVE,
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
        }
        $reader->close();
        @unlink($path);

        return $rows;
    }
}
