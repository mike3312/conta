<?php

namespace Tests\Unit\Fel;

use App\Services\Fel\FelXmlParserService;
use InvalidArgumentException;
use Tests\TestCase;

class FelXmlParserServiceTest extends TestCase
{
    public function test_it_extracts_a_sanitized_fel_document_without_fixed_prefixes(): void
    {
        $data = (new FelXmlParserService)->parse(dirname(__DIR__, 2).'/Fixtures/fel/documento-valido.xml');
        $this->assertSame('FACT', $data['general']['dte_type']);
        $this->assertSame('1234567', $data['issuer']['tax_id']);
        $this->assertSame('7654321', $data['receiver']['tax_id']);
        $this->assertSame('11111111-2222-4333-8444-555555555555', $data['certification']['authorization_uuid']);
        $this->assertSame('ABC123', $data['certification']['series']);
        $this->assertSame('100', $data['certification']['document_number']);
        $this->assertCount(1, $data['items']);
        $this->assertSame(12.0, $data['items'][0]['taxes'][0]['tax_amount']);
        $this->assertSame(12.0, $data['totals']['tax_total']);
        $this->assertSame(112.0, $data['totals']['grand_total']);
        $this->assertNotEmpty($data['complements']);
        $this->assertSame(1, $data['signatures']['count']);
    }

    public function test_it_rejects_malformed_xml_and_external_entities(): void
    {
        foreach ([
            '<broken>',
            '<!DOCTYPE x [<!ENTITY ext SYSTEM "file:///etc/passwd">]><x>&ext;</x>',
            str_repeat(' ', 5000).'<!DOCTYPE x [<!ENTITY ext "blocked">]><x>&ext;</x>',
        ] as $xml) {
            $path = tempnam(sys_get_temp_dir(), 'fel-test-');
            file_put_contents($path, $xml);
            try {
                (new FelXmlParserService)->parse($path);
                $this->fail('Se esperaba rechazo.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            } finally {
                unlink($path);
            }
        }
    }
}
