<?php

namespace Tests\Unit\Fel;

use App\Services\Fel\FelExcelParserService;
use App\Services\Fel\FelZipExtractorService;
use InvalidArgumentException;
use Tests\TestCase;
use ZipArchive;

class FelArchiveParserSecurityTest extends TestCase
{
    public function test_zip_rejects_path_traversal_entries(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fel-zip-security-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('../document.xml', '<document/>');
        $zip->close();

        try {
            $this->expectException(InvalidArgumentException::class);
            (new FelZipExtractorService)->process($path, fn () => $this->fail('No debe procesar un ZIP inseguro.'));
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_xlsx_returns_a_controlled_parser_error(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'fel-xlsx-invalid-');
        file_put_contents($path, 'not-an-xlsx');

        try {
            $this->expectException(InvalidArgumentException::class);
            iterator_to_array((new FelExcelParserService)->rows($path, 'xlsx'));
        } finally {
            @unlink($path);
        }
    }
}
