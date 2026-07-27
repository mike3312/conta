<?php

namespace Tests\Feature\Fel;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\FelDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fel\FelClassificationService;
use App\Services\Fel\FelDocumentImportService;
use App\Services\Fel\FelReclassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FelReclassificationWebTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->tenant = Tenant::create(['name' => 'Tenant web FEL', 'email' => 'web-fel@example.test', 'status' => 'ACTIVE']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->company = $this->company($this->tenant, 'Empresa activa', 'NIT-INCORRECTO');
        $this->attach($this->user, $this->company);
    }

    public function test_authorized_user_can_open_reclassification_screen(): void
    {
        $this->asActiveUser()->get(route('fel.reclassification.index'))
            ->assertOk()
            ->assertSee('Empresa activa')
            ->assertSee('Solo documentos con operación desconocida')
            ->assertSee('Todos los documentos FEL')
            ->assertDontSee('php artisan')
            ->assertDontSee('--company');
    }

    public function test_user_without_active_membership_cannot_open_preview_or_execute(): void
    {
        $outsider = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($outsider)->withSession(['company_id' => $this->company->id]);

        $this->get(route('fel.reclassification.index'))->assertForbidden();
        $this->post(route('fel.reclassification.preview'), ['scope' => 'unknown'])->assertForbidden();
        $this->post(route('fel.reclassification.execute'), ['scope' => 'unknown'])->assertForbidden();
    }

    public function test_company_id_sent_by_browser_is_ignored(): void
    {
        $document = $this->importDocument($this->company, $this->user);
        DB::table('companies')->where('id', $this->company->id)->update(['tax_id' => '7654321']);
        $otherCompany = $this->company($this->tenant, 'Empresa no activa', 'OTRO-NIT');
        $this->attach($this->user, $otherCompany);
        $otherDocument = $this->minimalDocument(90, company: $otherCompany);

        $this->asActiveUser()->post(route('fel.reclassification.preview'), [
            'scope' => 'unknown',
            'company_id' => $otherCompany->id,
        ])->assertRedirect(route('fel.reclassification.preview.show'));
        $preview = $this->get(route('fel.reclassification.preview.show'))->assertOk();
        $this->assertSame($this->company->id, $preview->viewData('company')->id);

        $this->post(route('fel.reclassification.execute'), [
            'scope' => 'unknown',
            'company_id' => $otherCompany->id,
        ])->assertRedirect(route('fel.reclassification.result'));

        $this->assertSame('PURCHASE', $document->fresh()->operation_type->value);
        $this->assertSame('UNKNOWN', $otherDocument->fresh()->operation_type->value);
    }

    public function test_company_without_tax_id_cannot_preview_or_execute(): void
    {
        DB::table('companies')->where('id', $this->company->id)->update(['tax_id' => '']);

        $this->asActiveUser()->get(route('fel.reclassification.index'))
            ->assertOk()
            ->assertSee('No es posible reclasificar los documentos porque la empresa activa no tiene un NIT configurado.')
            ->assertSee(route('companies.edit', $this->company));

        $this->post(route('fel.reclassification.preview'), ['scope' => 'unknown'])
            ->assertRedirect(route('fel.reclassification.index'))
            ->assertSessionHas('error');
        $this->post(route('fel.reclassification.execute'), ['scope' => 'unknown'])
            ->assertRedirect(route('fel.reclassification.index'));
    }

    public function test_preview_does_not_modify_data_and_only_unknown_scope_excludes_known_documents(): void
    {
        $unknown = $this->importDocument($this->company, $this->user);
        $known = $this->minimalDocument(2, operation: 'SALE', classification: 'GENERAL_SALE');
        DB::table('companies')->where('id', $this->company->id)->update(['tax_id' => '7654321']);

        $response = $this->asActiveUser()->preview('unknown')
            ->assertSee('Esta vista previa no modificó ningún documento.')
            ->assertSee('Confirmar reclasificación');

        $summary = $response->viewData('summary');
        $this->assertSame(1, $summary['candidates']);
        $this->assertSame(1, $summary['changed']);
        $this->assertSame('UNKNOWN', $unknown->fresh()->operation_type->value);
        $this->assertSame('SALE', $known->fresh()->operation_type->value);
    }

    public function test_all_scope_previews_all_documents(): void
    {
        $this->minimalDocument(1);
        $this->minimalDocument(2, operation: 'SALE', classification: 'GENERAL_SALE');

        $response = $this->asActiveUser()->preview('all');

        $this->assertSame(2, $response->viewData('summary')['candidates']);
    }

    public function test_execute_updates_classification_and_returns_complete_summary(): void
    {
        $document = $this->importDocument($this->company, $this->user);
        DB::table('companies')->where('id', $this->company->id)->update(['tax_id' => '7654321']);
        Log::spy();

        $this->asActiveUser()->preview('unknown');
        $this->post(route('fel.reclassification.execute'), ['scope' => 'unknown'])
            ->assertRedirect(route('fel.reclassification.result'))
            ->assertSessionHas('fel_reclassification_result');
        $this->get(route('fel.reclassification.result'))
            ->assertOk()
            ->assertSee('Los documentos FEL fueron reclasificados correctamente.')
            ->assertSee('Documentos revisados')
            ->assertSee('Documentos actualizados')
            ->assertSee('Revisión fiscal requerida')
            ->assertSee('No procesados');

        $document->refresh();
        $this->assertSame('PURCHASE', $document->operation_type->value);
        $this->assertSame('GENERAL_PURCHASE', $document->classification->value);
        Log::shouldHaveReceived('info')->with('FEL reclassification started', Mockery::on(fn (array $context) => $context['user_id'] === $this->user->id
            && $context['tenant_id'] === $this->tenant->id
            && $context['company_id'] === $this->company->id
            && $context['scope'] === 'unknown_only'))->once();
        Log::shouldHaveReceived('log')->with('info', 'FEL reclassification finished', Mockery::on(fn (array $context) => $context['changed'] === 1
            && $context['failed'] === 0
            && $context['result'] === 'success'))->once();
    }

    public function test_execute_requires_a_matching_preview_and_rejects_manipulated_scope(): void
    {
        $this->asActiveUser()->post(route('fel.reclassification.execute'), ['scope' => 'all'])->assertForbidden();

        $this->preview('unknown');
        $this->post(route('fel.reclassification.execute'), ['scope' => 'all'])->assertForbidden();
    }

    public function test_execute_preserves_original_content_human_review_and_accounting_data(): void
    {
        $document = $this->importDocument($this->company, $this->user);
        $reviewedAt = now()->startOfSecond();
        $document->update([
            'status' => 'APPROVED',
            'reviewed_by' => $this->user->id,
            'reviewed_at' => $reviewedAt,
            'observation' => 'Conservar esta observación',
            'rejection_reason' => 'Conservar motivo',
            'metadata' => ['origin' => 'test'],
        ]);
        $snapshot = $document->fresh()->only([
            'authorization_uuid', 'authorization_uuid_original', 'issuer_tax_id', 'receiver_tax_id',
            'issuer_name', 'receiver_name', 'issued_at', 'grand_total', 'currency', 'xml_path', 'xml_hash',
            'fel_import_batch_id', 'fiscal_status', 'status', 'reviewed_by', 'reviewed_at', 'observation',
            'rejection_reason', 'journal_entry_id', 'metadata',
        ]);
        $itemCount = $document->items()->count();
        $taxCount = $document->taxes()->count();
        $entryCount = DB::table('journal_entries')->count();
        DB::table('companies')->where('id', $this->company->id)->update(['tax_id' => '7654321']);

        $this->asActiveUser()->preview('unknown');
        $this->post(route('fel.reclassification.execute'), ['scope' => 'unknown'])
            ->assertRedirect(route('fel.reclassification.result'));

        $document->refresh();
        $this->assertEquals($snapshot, $document->only(array_keys($snapshot)));
        $this->assertSame($itemCount, $document->items()->count());
        $this->assertSame($taxCount, $document->taxes()->count());
        $this->assertSame($entryCount, DB::table('journal_entries')->count());
        $this->assertSame('APPROVED', $document->status->value);
    }

    public function test_an_individual_failure_does_not_cancel_other_documents(): void
    {
        Log::spy();
        $failed = $this->minimalDocument(1, receiverTaxId: 'FALLAR');
        $successful = $this->minimalDocument(2, receiverTaxId: $this->company->tax_id);
        $realClassifier = new FelClassificationService;
        $classifier = Mockery::mock(FelClassificationService::class);
        $classifier->shouldReceive('classify')->twice()->andReturnUsing(
            fn (array $data, ?string $taxId) => data_get($data, 'receiver.tax_id') === 'FALLAR'
                ? throw new RuntimeException('fallo simulado')
                : $realClassifier->classify($data, $taxId),
        );
        $service = new FelReclassificationService($classifier);

        $summary = $service->execute($this->company->id, onlyUnknown: true);

        $this->assertSame(1, $summary['failed']);
        $this->assertSame(1, $summary['processed']);
        $this->assertSame('UNKNOWN', $failed->fresh()->operation_type->value);
        $this->assertSame('PURCHASE', $successful->fresh()->operation_type->value);
        Log::shouldHaveReceived('error')->with('FEL reclassification document failed', Mockery::type('array'))->once();
    }

    public function test_empty_company_shows_no_documents_message(): void
    {
        $this->asActiveUser()->preview('unknown');
        $this->post(route('fel.reclassification.execute'), ['scope' => 'unknown'])
            ->assertRedirect(route('fel.reclassification.result'));
        $this->get(route('fel.reclassification.result'))
            ->assertOk()
            ->assertSee('No hay documentos para reclasificar.');
    }

    public function test_execute_cannot_be_requested_by_get(): void
    {
        $this->asActiveUser()->get(route('fel.reclassification.execute'))
            ->assertMethodNotAllowed();
    }

    public function test_company_switch_after_reclassification_uses_safe_fel_get_route(): void
    {
        $this->minimalDocument(1);
        $otherCompany = $this->company($this->tenant, 'Empresa destino', 'NIT-DESTINO');
        $this->attach($this->user, $otherCompany);

        $this->asActiveUser()->preview('unknown');
        $this->post(route('fel.reclassification.execute'), ['scope' => 'unknown'])
            ->assertRedirect(route('fel.reclassification.result'));
        $this->get(route('fel.reclassification.result'))->assertOk();

        $this->from(route('fel.reclassification.execute'))
            ->post(route('companies.switch'), [
                'company_id' => $otherCompany->id,
                'redirect_context' => 'fel',
            ])
            ->assertRedirect(route('fel-documents.index'));

        $this->assertSame($otherCompany->id, session('company_id'));
        $this->get(route('fel-documents.index'))->assertOk();
    }

    public function test_company_switch_maps_an_arbitrary_redirect_context_to_safe_dashboard(): void
    {
        $otherCompany = $this->company($this->tenant, 'Empresa destino', 'NIT-DESTINO');
        $this->attach($this->user, $otherCompany);

        $this->asActiveUser()->post(route('companies.switch'), [
            'company_id' => $otherCompany->id,
            'redirect_context' => 'fel/reclassification/execute',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame($otherCompany->id, session('company_id'));
    }

    public function test_invalid_company_switch_never_redirects_back_to_post_url(): void
    {
        $this->asActiveUser()->from(route('fel.reclassification.execute'))
            ->post(route('companies.switch'), [
                'company_id' => 999999,
                'redirect_context' => 'fel',
            ])
            ->assertRedirect(route('fel-documents.index'))
            ->assertSessionHasErrors('company_id');
    }

    public function test_fel_tray_uses_bootstrap_pagination_without_tailwind_svg_arrows(): void
    {
        for ($index = 1; $index <= 26; $index++) {
            $this->minimalDocument($index);
        }

        $this->asActiveUser()->get(route('fel-documents.index'))
            ->assertOk()
            ->assertSee('pagination')
            ->assertSee('page-item')
            ->assertSee('page-link')
            ->assertDontSee('<svg', false)
            ->assertDontSee('w-5 h-5');
    }

    private function asActiveUser(): static
    {
        return $this->actingAs($this->user)->withSession(['company_id' => $this->company->id]);
    }

    private function preview(string $scope): TestResponse
    {
        $this->post(route('fel.reclassification.preview'), compact('scope'))
            ->assertRedirect(route('fel.reclassification.preview.show'));

        return $this->get(route('fel.reclassification.preview.show'))->assertOk();
    }

    private function importDocument(Company $company, User $user): FelDocument
    {
        app(FelDocumentImportService::class)->import(new UploadedFile(
            base_path('tests/Fixtures/fel/documento-valido.xml'),
            'documento.xml',
            'application/xml',
            null,
            true,
        ), $company, $user);

        return FelDocument::query()->where('company_id', $company->id)->firstOrFail();
    }

    private function minimalDocument(
        int $index,
        string $operation = 'UNKNOWN',
        string $classification = 'UNCLASSIFIED',
        ?string $receiverTaxId = null,
        ?Company $company = null,
    ): FelDocument {
        $company ??= $this->company;

        return FelDocument::create([
            'tenant_id' => $company->tenant_id,
            'company_id' => $company->id,
            'authorization_uuid' => sprintf('00000000-0000-4000-8000-%012d', $index),
            'dte_type' => 'FACT',
            'currency' => 'GTQ',
            'source_type' => 'XML',
            'data_level' => 'FULL_DETAIL',
            'operation_type' => $operation,
            'classification' => $classification,
            'status' => 'PENDING',
            'fiscal_status' => 'ACTIVE',
            'issued_at' => now()->subMinutes($index),
            'issuer_tax_id' => 'EMISOR-'.$index,
            'issuer_name' => 'Emisor '.$index,
            'receiver_tax_id' => $receiverTaxId,
            'receiver_name' => 'Receptor '.$index,
            'grand_total' => 100,
            'imported_by' => $this->user->id,
        ]);
    }

    private function company(Tenant $tenant, string $name, ?string $taxId): Company
    {
        return Company::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'legal_name' => $name.' S.A.',
            'tax_id' => $taxId,
            'status' => CompanyStatus::ACTIVE,
        ]);
    }

    private function attach(User $user, Company $company): void
    {
        $user->companies()->attach($company, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);
    }
}
