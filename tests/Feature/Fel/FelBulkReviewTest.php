<?php

namespace Tests\Feature\Fel;

use App\Enums\AccountingPeriodStatus;
use App\Enums\CompanyStatus;
use App\Enums\FelDocumentStatus;
use App\Enums\FelFiscalStatus;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FelDocument;
use App\Models\FiscalDocument;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FelBulkReviewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Company $company;

    private AccountingPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Tenant FEL bulk', 'email' => 'bulk@example.test', 'status' => 'ACTIVE']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->company = $this->company('Empresa bulk', '7654321');
        $this->user->companies()->attach($this->company, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);
        $this->period = AccountingPeriod::create([
            'company_id' => $this->company->id, 'name' => 'Julio', 'start_date' => '2026-07-01',
            'end_date' => '2026-07-31', 'status' => AccountingPeriodStatus::OPEN,
        ]);
    }

    public function test_bulk_approval_is_partial_tenant_safe_audited_idempotent_and_preserves_filters(): void
    {
        $valid = $this->fel('BULK-VALID');
        $voided = $this->fel('BULK-VOIDED', ['fiscal_status' => FelFiscalStatus::VOIDED]);
        $otherCompany = $this->company('Otra empresa', '9999999');
        $other = $this->fel('BULK-OTHER', ['company_id' => $otherCompany->id]);
        $beforeEntries = DB::table('journal_entries')->count();

        $response = $this->asCompany()->post(route('fel-documents.bulk-review'), [
            'document_ids' => [$valid->id, $voided->id, $other->id],
            'company_id' => $otherCompany->id,
            'action' => 'APPROVED',
            'filters' => ['status' => 'PENDING', 'name' => 'Proveedor'],
        ]);

        $response->assertRedirect(route('fel-documents.index', ['status' => 'PENDING', 'name' => 'Proveedor']))
            ->assertSessionHas('fel_bulk_review_result', fn (array $result) => $result['processed'] === 1 && $result['skipped'] === 1 && $result['errors'] === 1);
        $this->assertSame(FelDocumentStatus::APPROVED, $valid->fresh()->status);
        $this->assertSame(FelDocumentStatus::PENDING, $voided->fresh()->status);
        $this->assertSame(FelDocumentStatus::PENDING, $other->fresh()->status);
        $this->assertDatabaseHas('fel_document_review_logs', ['fel_document_id' => $valid->id, 'action_type' => 'BULK', 'previous_status' => 'PENDING', 'new_status' => 'APPROVED']);
        $this->assertDatabaseHas('fel_document_review_logs', ['fel_document_id' => $voided->id, 'action_type' => 'BULK']);
        $this->assertDatabaseMissing('fel_document_review_logs', ['fel_document_id' => $other->id]);
        $this->assertDatabaseHas('fiscal_documents', ['fel_document_id' => $valid->id, 'company_id' => $this->company->id]);
        $this->assertSame(1, FiscalDocument::where('fel_document_id', $valid->id)->count());
        $this->assertSame($beforeEntries, DB::table('journal_entries')->count());

        $this->post(route('fel-documents.bulk-review'), ['document_ids' => [$valid->id], 'action' => 'APPROVED'])
            ->assertSessionHas('fel_bulk_review_result', fn (array $result) => $result['processed'] === 0 && $result['skipped'] === 1);
        $this->assertSame(1, FiscalDocument::where('fel_document_id', $valid->id)->count());
    }

    public function test_bulk_observe_and_reject_require_reason_and_never_void_documents(): void
    {
        $observed = $this->fel('BULK-OBSERVE');
        $rejected = $this->fel('BULK-REJECT');

        $this->asCompany()->post(route('fel-documents.bulk-review'), [
            'document_ids' => [$observed->id], 'action' => 'OBSERVED',
        ])->assertSessionHasErrors('reason');
        $this->post(route('fel-documents.bulk-review'), [
            'document_ids' => [$rejected->id], 'action' => 'REJECTED',
        ])->assertSessionHasErrors('reason');

        $this->post(route('fel-documents.bulk-review'), [
            'document_ids' => [$observed->id], 'action' => 'OBSERVED', 'reason' => 'Revisar datos del tercero',
        ])->assertSessionHasNoErrors();
        $this->post(route('fel-documents.bulk-review'), [
            'document_ids' => [$rejected->id], 'action' => 'REJECTED', 'reason' => 'Documento improcedente',
        ])->assertSessionHasNoErrors();

        $this->assertSame(FelDocumentStatus::OBSERVED, $observed->fresh()->status);
        $this->assertSame(FelFiscalStatus::ACTIVE, $observed->fresh()->fiscal_status);
        $this->assertSame(FelDocumentStatus::REJECTED, $rejected->fresh()->status);
        $this->assertSame(FelFiscalStatus::ACTIVE, $rejected->fresh()->fiscal_status);
        $this->assertDatabaseHas('fel_document_review_logs', ['fel_document_id' => $observed->id, 'reason' => 'Revisar datos del tercero']);
        $this->assertDatabaseHas('fel_document_review_logs', ['fel_document_id' => $rejected->id, 'reason' => 'Documento improcedente']);
    }

    public function test_closed_period_document_is_skipped_without_stopping_valid_document(): void
    {
        $closedPeriod = AccountingPeriod::create([
            'company_id' => $this->company->id, 'name' => 'Junio cerrado', 'start_date' => '2026-06-01',
            'end_date' => '2026-06-30', 'status' => AccountingPeriodStatus::CLOSED,
        ]);
        $blocked = $this->fel('BULK-CLOSED', ['issued_at' => '2026-06-15 10:00:00']);
        $this->fiscal($blocked, $closedPeriod);
        $valid = $this->fel('BULK-OPEN');

        $this->asCompany()->post(route('fel-documents.bulk-review'), [
            'document_ids' => [$blocked->id, $valid->id], 'action' => 'OBSERVED', 'reason' => 'Control masivo',
        ])->assertSessionHas('fel_bulk_review_result', fn (array $result) => $result['processed'] === 1 && $result['skipped'] === 1);

        $this->assertSame(FelDocumentStatus::PENDING, $blocked->fresh()->status);
        $this->assertSame(FelDocumentStatus::OBSERVED, $valid->fresh()->status);
    }

    public function test_master_checkbox_only_targets_eligible_documents_on_current_page_and_selection_is_not_persisted(): void
    {
        foreach (range(1, 30) as $index) {
            $this->fel('PAGE-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT));
        }
        $this->fel('PAGE-VOIDED', ['fiscal_status' => FelFiscalStatus::VOIDED]);

        $first = $this->asCompany()->get(route('fel-documents.index'))->assertOk();
        $this->assertSame(25, substr_count($first->getContent(), 'class="form-check-input document-select"'));
        $first->assertSee('id="selectVisible"', false)->assertSee('document-select:not(:disabled)', false);

        $second = $this->get(route('fel-documents.index', ['page' => 2]))->assertOk();
        $this->assertSame(6, substr_count($second->getContent(), 'class="form-check-input document-select"'));
        $this->assertSame(0, substr_count($second->getContent(), 'document-select" type="checkbox" name="document_ids[]" value=" checked'));

        $filtered = $this->get(route('fel-documents.index', ['status' => 'REJECTED']))->assertOk();
        $this->assertSame(0, substr_count($filtered->getContent(), 'class="form-check-input document-select"'));
    }

    public function test_inactive_pivot_cannot_execute_bulk_review(): void
    {
        $document = $this->fel('BULK-INACTIVE');
        $this->user->companies()->updateExistingPivot($this->company->id, ['is_active' => false]);

        $this->actingAs($this->user)->withSession(['company_id' => $this->company->id])
            ->post(route('fel-documents.bulk-review'), ['document_ids' => [$document->id], 'action' => 'APPROVED'])
            ->assertForbidden();
        $this->assertSame(FelDocumentStatus::PENDING, $document->fresh()->status);
    }

    public function test_individual_review_reuses_service_and_records_individual_audit(): void
    {
        $document = $this->fel('INDIVIDUAL-AUDIT');

        $this->asCompany()->post(route('fel-documents.observe', $document), [
            'observation' => 'Verificación individual',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(FelDocumentStatus::OBSERVED, $document->fresh()->status);
        $this->assertDatabaseHas('fel_document_review_logs', [
            'fel_document_id' => $document->id,
            'previous_status' => 'PENDING',
            'new_status' => 'OBSERVED',
            'reason' => 'Verificación individual',
            'action_type' => 'INDIVIDUAL',
        ]);
    }

    private function asCompany(): static
    {
        return $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    private function fel(string $uuid, array $overrides = []): FelDocument
    {
        $companyId = $overrides['company_id'] ?? $this->company->id;

        return FelDocument::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'company_id' => $companyId,
            'authorization_uuid' => $uuid,
            'dte_type' => 'FACT',
            'currency' => 'GTQ',
            'source_type' => 'XML',
            'data_level' => 'FULL_DETAIL',
            'operation_type' => 'PURCHASE',
            'classification' => 'GENERAL_PURCHASE',
            'status' => FelDocumentStatus::PENDING,
            'fiscal_status' => FelFiscalStatus::ACTIVE,
            'issued_at' => '2026-07-15 10:00:00',
            'issuer_tax_id' => '1234567',
            'issuer_name' => 'Proveedor válido',
            'receiver_tax_id' => $companyId === $this->company->id ? $this->company->tax_id : '9999999',
            'receiver_name' => 'Empresa receptora',
            'subtotal' => '100.00',
            'taxable_total' => '100.00',
            'tax_total' => '12.00',
            'other_tax_total' => '0.00',
            'grand_total' => '112.00',
            'requires_tax_review' => false,
            'requires_accounting_review' => true,
            'signature_count' => 1,
            'imported_by' => $this->user->id,
        ], $overrides));
    }

    private function fiscal(FelDocument $fel, AccountingPeriod $period): FiscalDocument
    {
        return FiscalDocument::create([
            'company_id' => $this->company->id,
            'fel_document_id' => $fel->id,
            'accounting_period_id' => $period->id,
            'direction' => FiscalDocumentDirection::PURCHASE,
            'document_type' => FiscalDocumentType::INVOICE,
            'tax_category' => FiscalTaxCategory::GOODS,
            'document_date' => $fel->issued_at->toDateString(),
            'third_party_name' => 'Proveedor',
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
}
