<?php

namespace Tests\Feature\Fel;

use App\Enums\CompanyStatus;
use App\Exceptions\FelImportException;
use App\Models\Company;
use App\Models\FelDocument;
use App\Models\FelImportBatch;
use App\Models\FelImportRow;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fel\FelXmlParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;
use ZipArchive;

class FelImportDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_real_namespace_length_and_mysql_sensitive_values_are_persistable(): void
    {
        [$user, $company] = $this->context('7654321');

        $this->import($user, $company, $this->fixtureUpload('documento-valido.xml'));

        $document = FelDocument::firstOrFail();
        $this->assertSame(1, $document->importBatch->total_files);
        $raw = DB::table('fel_documents')->find($document->id);
        $this->assertSame('http://www.sat.gob.gt/dte/fel/0.2.0', $document->fel_version);
        $this->assertGreaterThan(30, strlen($document->fel_version));
        $this->assertSame('XML', $raw->source_type);
        $this->assertSame('FULL_DETAIL', $raw->data_level);
        $this->assertSame('PENDING', $raw->status);
        $this->assertSame('112.000000', $document->grand_total);
        $this->assertIsArray($document->complements);
        $this->assertNotNull($raw->tenant_id);
        $this->assertNotNull($raw->company_id);
        $this->assertNotNull($raw->imported_by);
        $this->assertNotNull($raw->authorization_uuid);
        $this->assertNotNull($raw->issued_at);
        $this->assertNull($raw->journal_entry_id);
    }

    public function test_missing_uuid_and_malformed_xml_receive_specific_codes(): void
    {
        [$user, $company] = $this->context('7654321');
        $this->import($user, $company, $this->fixtureUpload('documento-sin-uuid.xml'));
        $this->assertDatabaseHas('fel_import_rows', [
            'status' => 'FAILED', 'error_code' => 'FEL-XML-UUID', 'processing_stage' => 'xml_uuid',
        ]);

        $this->import($user, $company, $this->fixtureUpload('documento-malformado.xml'));
        $this->assertDatabaseHas('fel_import_rows', [
            'status' => 'FAILED', 'error_code' => 'FEL-XML-PARSE', 'processing_stage' => 'xml_parse',
        ]);
        $this->assertDatabaseCount('fel_documents', 0);
    }

    public function test_zip_commits_valid_documents_and_rolls_back_only_invalid_xml(): void
    {
        [$user, $company] = $this->context('7654321');
        $xml = file_get_contents(base_path('tests/Fixtures/fel/documento-valido.xml'));
        $path = tempnam(sys_get_temp_dir(), 'fel-mixed-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('uno.xml', $xml);
        $zip->addFromString('dos.xml', str_replace('11111111-2222-4333-8444-555555555555', '22222222-3333-4444-8555-666666666666', $xml));
        $zip->addFromString('malformado.xml', file_get_contents(base_path('tests/Fixtures/fel/documento-malformado.xml')));
        $zip->close();

        try {
            $this->import($user, $company, new UploadedFile($path, 'lote.zip', 'application/zip', null, true));
        } finally {
            @unlink($path);
        }

        $batch = FelImportBatch::firstOrFail();
        $this->assertSame('ZIP_XML', $batch->source_type->value);
        $this->assertSame(3, $batch->total_files);
        $this->assertSame(3, $batch->total_records);
        $this->assertSame(2, $batch->successful_records);
        $this->assertSame(1, $batch->failed_records);
        $this->assertDatabaseCount('fel_documents', 2);
        $this->assertDatabaseHas('fel_import_rows', ['error_code' => 'FEL-XML-PARSE']);
    }

    public function test_csv_parses_localized_numbers_and_dates(): void
    {
        [$user, $company] = $this->context('7654321');
        $this->import($user, $company, $this->fixtureUpload('resumen-valido.csv', 'text/csv'));

        $document = FelDocument::firstOrFail();
        $this->assertSame('1234.560000', $document->grand_total);
        $this->assertSame('2026-07-20 10:30:00', $document->issued_at->format('Y-m-d H:i:s'));
        $this->assertSame('CSV', $document->source_type->value);
    }

    public function test_xlsx_with_zip_mime_imports_and_missing_headers_are_diagnostic(): void
    {
        [$user, $company] = $this->context('7654321');
        [$upload, $path] = $this->xlsxUpload(true);
        try {
            $this->import($user, $company, $upload);
        } finally {
            @unlink($path);
        }
        $this->assertDatabaseHas('fel_documents', [
            'authorization_uuid' => 'AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE',
            'source_type' => 'EXCEL',
        ]);

        [$badUpload, $badPath] = $this->xlsxUpload(false);
        try {
            $this->import($user, $company, $badUpload);
        } finally {
            @unlink($badPath);
        }
        $this->assertDatabaseCount('fel_import_batches', 2);
        $this->assertDatabaseHas('fel_import_rows', [
            'status' => 'FAILED', 'error_code' => 'FEL-EXCEL-HEADERS', 'processing_stage' => 'spreadsheet_headers',
        ]);
    }

    public function test_parser_exposes_safe_code_without_xml_contents(): void
    {
        try {
            app(FelXmlParserService::class)->parse(base_path('tests/Fixtures/fel/documento-sin-uuid.xml'));
            $this->fail('El XML sin UUID debía fallar.');
        } catch (FelImportException $exception) {
            $this->assertSame('FEL-XML-UUID', $exception->errorCode);
            $this->assertStringNotContainsString('<dte:', $exception->getMessage());
        }
    }

    public function test_technical_log_contains_context_and_never_the_xml_body(): void
    {
        Log::spy();
        [$user, $company] = $this->context('7654321');
        $this->import($user, $company, $this->fixtureUpload('documento-malformado.xml'));

        Log::shouldHaveReceived('error')->with('FEL XML parser failed', Mockery::on(function (array $context) use ($company): bool {
            return $context['error_code'] === 'FEL-XML-PARSE'
                && $context['processing_stage'] === 'xml_parse'
                && $context['company_id'] === $company->id
                && $context['tenant_id'] === $company->tenant_id
                && isset($context['batch_id'], $context['source_filename'], $context['file_type'], $context['exception_class'], $context['technical_message'], $context['exception_file'], $context['exception_line'], $context['stack_trace'])
                && ! str_contains($context['technical_message'], '<dte:');
        }))->once();
    }

    public function test_binary_xls_is_rejected_explicitly_instead_of_being_marked_imported(): void
    {
        [$user, $company] = $this->context('7654321');
        $upload = UploadedFile::fake()->createWithContent('legacy.xls', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1binary-xls");
        $this->import($user, $company, $upload);

        $this->assertDatabaseCount('fel_documents', 0);
        $this->assertDatabaseHas('fel_import_rows', [
            'status' => 'FAILED', 'error_code' => 'FEL-EXCEL-READ', 'processing_stage' => 'spreadsheet_read',
        ]);
        $this->assertStringContainsString('XLS binario', FelImportRow::firstOrFail()->message);
    }

    public function test_invalid_csv_row_does_not_cancel_valid_rows(): void
    {
        [$user, $company] = $this->context('7654321');
        $csv = "Número de autorización,Fecha de emisión,Tipo de DTE,NIT del emisor,Nombre completo del emisor,ID del receptor,Gran Total\n".
            "AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE,2026-07-20,FACT,1234567,Emisor,7654321,100.00\n".
            ",fecha-inválida,FACT,1234567,Emisor,7654321,50.00\n";
        $upload = UploadedFile::fake()->createWithContent('mixto.csv', $csv);
        $this->import($user, $company, $upload);

        $batch = FelImportBatch::firstOrFail();
        $this->assertSame(2, $batch->total_records);
        $this->assertSame(1, $batch->successful_records);
        $this->assertSame(1, $batch->failed_records);
        $this->assertDatabaseCount('fel_documents', 1);
        $this->assertDatabaseHas('fel_import_rows', ['status' => 'FAILED', 'error_code' => 'FEL-EXCEL-ROW']);
    }

    private function import(User $user, Company $company, UploadedFile $file): void
    {
        $this->actingAs($user)
            ->withSession(['company_id' => $company->id])
            ->post(route('fel-imports.store'), ['files' => [$file]])
            ->assertSessionHasNoErrors();
    }

    private function fixtureUpload(string $name, string $mime = 'application/xml'): UploadedFile
    {
        return new UploadedFile(base_path('tests/Fixtures/fel/'.$name), $name, $mime, null, true);
    }

    private function xlsxUpload(bool $validHeaders): array
    {
        $path = tempnam(sys_get_temp_dir(), 'fel-xlsx-');
        $writer = new Writer;
        $writer->openToFile($path);
        $headers = $validHeaders
            ? ['Número de autorización', 'Fecha de emisión', 'Tipo de DTE', 'NIT del emisor', 'Nombre completo del emisor', 'ID del receptor', 'Gran Total']
            : ['Columna desconocida', 'Otra columna'];
        $writer->addRow(Row::fromValues($headers));
        $writer->addRow(Row::fromValues($validHeaders
            ? ['AAAAAAAA-BBBB-4CCC-8DDD-EEEEEEEEEEEE', 46223.5, 'FACT', '1234567', 'Emisor Sanitizado', '7654321', 250.75]
            : ['valor', 'otro']));
        $writer->close();

        return [new UploadedFile($path, $validHeaders ? 'resumen.xlsx' : 'sin-columnas.xlsx', 'application/zip', null, true), $path];
    }

    private function context(string $taxId): array
    {
        $tenant = Tenant::create(['name' => 'Tenant '.$taxId, 'email' => $taxId.'@example.test', 'status' => 'ACTIVE']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'name' => 'Empresa '.$taxId,
            'legal_name' => 'Empresa '.$taxId.' S.A.',
            'tax_id' => $taxId,
            'status' => CompanyStatus::ACTIVE,
        ]);
        $user->companies()->attach($company, ['is_owner' => true, 'is_active' => true, 'joined_at' => now()]);

        return [$user, $company];
    }
}
