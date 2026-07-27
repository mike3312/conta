<?php

namespace Tests\Feature\Fel;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\FelDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fel\FelDocumentImportService;
use App\Services\Fel\FelReclassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FelReclassifyCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $tenant = Tenant::create(['name' => 'Tenant FEL', 'email' => 'fel-reclassify@example.test', 'status' => 'ACTIVE']);
        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->company = Company::create([
            'tenant_id' => $tenant->id,
            'name' => 'Empresa FEL',
            'legal_name' => 'Empresa FEL S.A.',
            'tax_id' => 'NIT-INCORRECTO',
            'status' => CompanyStatus::ACTIVE,
        ]);
        $this->user->companies()->attach($this->company, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);
    }

    public function test_dry_run_uses_current_company_nit_without_updating_document(): void
    {
        $document = $this->importUnknownDocument();
        DB::table('companies')->where('id', $this->company->id)->update(['tax_id' => '7654321']);
        $summary = app(FelReclassificationService::class)->run($this->company->id, onlyUnknown: true, dryRun: true);
        $this->assertSame(1, $summary['candidates']);
        $this->assertSame(1, $summary['changed']);

        $exit = Artisan::call('fel:reclassify', [
            '--company' => $this->company->id,
            '--only-unknown' => true,
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('DRY-RUN', Artisan::output());
        $this->assertSame('UNKNOWN', $document->fresh()->operation_type->value);
        $this->assertSame('UNCLASSIFIED', $document->fresh()->classification->value);
    }

    public function test_command_reclassifies_without_touching_review_uuid_details_taxes_or_journal(): void
    {
        $document = $this->importUnknownDocument();
        $snapshot = [
            'uuid' => $document->authorization_uuid,
            'status' => $document->status->value,
            'reviewed_at' => $document->reviewed_at,
            'reviewed_by' => $document->reviewed_by,
            'items' => $document->items()->count(),
            'taxes' => $document->taxes()->count(),
            'journal_entry_id' => $document->journal_entry_id,
        ];
        DB::table('companies')->where('id', $this->company->id)->update(['tax_id' => '7654321']);

        $this->artisan('fel:reclassify', [
            '--company' => $this->company->id,
            '--only-unknown' => true,
        ])->assertSuccessful();

        $document->refresh();
        $this->assertSame('PURCHASE', $document->operation_type->value);
        $this->assertSame('GENERAL_PURCHASE', $document->classification->value);
        $this->assertSame($snapshot['uuid'], $document->authorization_uuid);
        $this->assertSame($snapshot['status'], $document->status->value);
        $this->assertEquals($snapshot['reviewed_at'], $document->reviewed_at);
        $this->assertSame($snapshot['reviewed_by'], $document->reviewed_by);
        $this->assertSame($snapshot['items'], $document->items()->count());
        $this->assertSame($snapshot['taxes'], $document->taxes()->count());
        $this->assertSame($snapshot['journal_entry_id'], $document->journal_entry_id);
    }

    public function test_reviewed_documents_require_explicit_include_reviewed_option(): void
    {
        $document = $this->importUnknownDocument();
        $reviewedAt = now()->startOfSecond();
        $document->update(['status' => 'APPROVED', 'reviewed_by' => $this->user->id, 'reviewed_at' => $reviewedAt]);
        DB::table('companies')->where('id', $this->company->id)->update(['tax_id' => '7654321']);

        $this->artisan('fel:reclassify', ['--company' => $this->company->id, '--only-unknown' => true])
            ->expectsOutputToContain('Revisados omitidos')
            ->assertSuccessful();
        $this->assertSame('UNKNOWN', $document->fresh()->operation_type->value);

        $this->artisan('fel:reclassify', [
            '--company' => $this->company->id,
            '--only-unknown' => true,
            '--include-reviewed' => true,
        ])->assertSuccessful();
        $document->refresh();
        $this->assertSame('PURCHASE', $document->operation_type->value);
        $this->assertSame('APPROVED', $document->status->value);
        $this->assertTrue($reviewedAt->equalTo($document->reviewed_at));
        $this->assertSame($this->user->id, $document->reviewed_by);
    }

    public function test_new_import_always_uses_latest_persisted_nit_even_with_stale_company_object(): void
    {
        $staleCompany = $this->company;
        DB::table('companies')->where('id', $this->company->id)->update(['tax_id' => '7654321']);

        app(FelDocumentImportService::class)->import($this->xmlUpload(), $staleCompany, $this->user);

        $document = FelDocument::firstOrFail();
        $this->assertSame('PURCHASE', $document->operation_type->value);
        $this->assertSame('GENERAL_PURCHASE', $document->classification->value);
    }

    private function importUnknownDocument(): FelDocument
    {
        app(FelDocumentImportService::class)->import($this->xmlUpload(), $this->company, $this->user);

        $document = FelDocument::firstOrFail();
        $this->assertSame('UNKNOWN', $document->operation_type->value);

        return $document;
    }

    private function xmlUpload(): UploadedFile
    {
        return new UploadedFile(
            base_path('tests/Fixtures/fel/documento-valido.xml'),
            'documento.xml',
            'application/xml',
            null,
            true,
        );
    }
}
