<?php

namespace Tests\Feature\Fel;

use App\Enums\CompanyStatus;
use App\Enums\FelDocumentStatus;
use App\Models\Company;
use App\Models\FelDocument;
use App\Models\FelImportRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;
use ZipArchive;

class FelImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'), 'Las pruebas FEL solo pueden ejecutarse con SQLite aislado.');
        $this->assertSame(':memory:', config('database.connections.sqlite.database'), 'Las pruebas FEL solo pueden ejecutarse en memoria.');
        Storage::fake('local');
    }

    public function test_xml_import_creates_pending_document_without_accounting_entries(): void
    {
        Log::spy();
        [$user, $company] = $this->context('7654321');
        $response = $this->actingAs($user)->withSession(['company_id' => $company->id])->post(route('fel-imports.store'), ['files' => [$this->xmlUpload()]]);
        $response->assertSessionHasNoErrors();
        if (FelDocument::count() === 0) {
            $this->fail('La importación no creó el documento: '.json_encode(FelImportRow::pluck('message')->all()));
        }
        $document = FelDocument::firstOrFail();
        $response->assertRedirect(route('fel-imports.show', $document->fel_import_batch_id));
        $this->assertSame(FelDocumentStatus::PENDING, $document->status);
        $this->assertSame('11111111-2222-4333-8444-555555555555', $document->authorization_uuid);
        $this->assertCount(1, $document->items);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertNull($document->journal_entry_id);
        Storage::disk('local')->assertExists($document->xml_path);
        $this->assertStringStartsWith('fel/companies/'.$company->uuid.'/', $document->xml_path);
        $this->assertStringNotContainsString('sin-tenant', $document->xml_path);
        $this->get(route('fel-documents.index'))->assertOk()->assertSee('Emisor Sanitizado');
        $this->get(route('fel-documents.show', $document))->assertOk()->assertSee('No generada');
        Log::shouldHaveReceived('info')->with('FEL import started', Mockery::on(fn (array $context) => $context['company_id'] === $company->id && ! isset($context['filename'], $context['uuid'], $context['tax_id'])))->once();
        Log::shouldHaveReceived('info')->with('FEL XML processed', Mockery::type('array'))->once();
        Log::shouldHaveReceived('info')->with('FEL import finished', Mockery::type('array'))->once();
    }

    public function test_duplicate_xml_is_recorded_without_duplicate_document(): void
    {
        Log::spy();
        [$user, $company] = $this->context('7654321');
        $this->actingAs($user)->withSession(['company_id' => $company->id])->post(route('fel-imports.store'), ['files' => [$this->xmlUpload()]]);
        $this->post(route('fel-imports.store'), ['files' => [$this->xmlUpload()]])->assertRedirect();
        $this->assertDatabaseCount('fel_documents', 1);
        $this->assertDatabaseHas('fel_import_rows', ['status' => 'DUPLICATE']);
        Log::shouldHaveReceived('info')->with('FEL document duplicate detected', Mockery::type('array'))->once();
    }

    public function test_approval_does_not_create_a_journal_entry(): void
    {
        [$user, $company] = $this->context('7654321');
        $this->actingAs($user)->withSession(['company_id' => $company->id])->post(route('fel-imports.store'), ['files' => [$this->xmlUpload()]]);
        $document = FelDocument::firstOrFail();
        $this->post(route('fel-documents.approve', $document))->assertRedirect();
        $this->assertSame(FelDocumentStatus::APPROVED, $document->fresh()->status);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_user_cannot_view_approve_or_download_another_company_document(): void
    {
        [$owner, $ownerCompany] = $this->context('7654321');
        $this->actingAs($owner)->withSession(['company_id' => $ownerCompany->id])->post(route('fel-imports.store'), ['files' => [$this->xmlUpload()]]);
        $document = FelDocument::firstOrFail();
        [$other, $otherCompany] = $this->context('8888888');
        $this->actingAs($other)->withSession(['company_id' => $otherCompany->id]);
        $this->get(route('fel-documents.show', $document))->assertForbidden();
        $this->post(route('fel-documents.approve', $document))->assertForbidden();
        $this->get(route('fel-documents.xml', $document))->assertForbidden();
    }

    public function test_zip_import_keeps_valid_xml_when_another_xml_is_invalid(): void
    {
        Log::spy();
        [$user, $company] = $this->context('7654321');
        $path = tempnam(sys_get_temp_dir(), 'fel-zip-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('subcarpeta/valido.xml', file_get_contents(base_path('tests/Fixtures/fel/documento-valido.xml')));
        $zip->addFromString('incorrecto.xml', '<documento>');
        $zip->addFromString('ignorado.pdf', 'no es xml');
        $zip->close();
        try {
            $upload = new UploadedFile($path, 'documentos.zip', 'application/zip', null, true);
            $this->actingAs($user)->withSession(['company_id' => $company->id])->post(route('fel-imports.store'), ['files' => [$upload]])->assertRedirect();
            $this->assertDatabaseCount('fel_documents', 1);
            $this->assertDatabaseHas('fel_import_batches', ['total_records' => 2, 'successful_records' => 1, 'failed_records' => 1]);
            Log::shouldHaveReceived('info')->with('FEL ZIP processed', Mockery::type('array'))->once();
            Log::shouldHaveReceived('error')->with('FEL XML parser failed', Mockery::type('array'))->once();
        } finally {
            @unlink($path);
        }
    }

    public function test_csv_summary_is_enriched_by_later_xml_without_creating_a_second_document(): void
    {
        Log::spy();
        [$user, $company] = $this->context('7654321');
        $csv = "Número de autorización,Fecha de emisión,Tipo de DTE,NIT del emisor,Nombre completo del emisor,ID del receptor,Gran Total,IVA\n".
            "11111111-2222-4333-8444-555555555555,2026-07-20 10:00:00,FACT,1234567,Emisor Sanitizado,7654321,112.00,12.00\n";
        $upload = UploadedFile::fake()->createWithContent('resumen.csv', $csv);
        $this->actingAs($user)->withSession(['company_id' => $company->id])->post(route('fel-imports.store'), ['files' => [$upload]])->assertRedirect();
        $this->assertSame('SUMMARY', FelDocument::firstOrFail()->data_level->value);
        $this->post(route('fel-imports.store'), ['files' => [$this->xmlUpload()]])->assertRedirect();
        $this->assertDatabaseCount('fel_documents', 1);
        $document = FelDocument::firstOrFail();
        $this->assertSame('FULL_DETAIL', $document->data_level->value);
        $this->assertCount(1, $document->items);
        $this->assertDatabaseCount('fiscal_documents', 1);
        $this->assertSame($document->id, $document->fiscalDocument->fel_document_id);
        $this->assertDatabaseHas('fel_import_rows', ['status' => 'ENRICHED', 'fel_document_id' => $document->id]);
        Log::shouldHaveReceived('info')->with('FEL Excel/CSV processed', Mockery::type('array'))->once();
        Log::shouldHaveReceived('info')->with('FEL document enriched', Mockery::type('array'))->once();
    }

    private function context(string $taxId): array
    {
        $user = User::factory()->create();
        $company = Company::create(['name' => 'Empresa '.$taxId, 'legal_name' => 'Empresa '.$taxId.' S.A.', 'tax_id' => $taxId, 'status' => CompanyStatus::ACTIVE]);
        $user->companies()->attach($company->id, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);

        return [$user, $company];
    }

    private function xmlUpload(): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/fel/documento-valido.xml'), 'documento.xml', 'application/xml', null, true);
    }
}
