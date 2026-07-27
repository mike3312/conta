<?php

namespace Tests\Unit\Fiscal;

use App\Enums\FiscalDocumentType;
use App\Services\Fiscal\FelDocumentTypeMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FelDocumentTypeMapperTest extends TestCase
{
    #[DataProvider('knownTypes')]
    public function test_known_dte_types_are_mapped_once(string $dte, FiscalDocumentType $expected): void
    {
        $result = (new FelDocumentTypeMapper)->map($dte);

        $this->assertSame($expected, $result['type']);
        $this->assertSame([], $result['warnings']);
    }

    public static function knownTypes(): array
    {
        return [
            ['FACT', FiscalDocumentType::INVOICE],
            ['FCAM', FiscalDocumentType::EXCHANGE_INVOICE],
            ['NCRE', FiscalDocumentType::CREDIT_NOTE],
            ['NDEB', FiscalDocumentType::DEBIT_NOTE],
            ['FPEQ', FiscalDocumentType::INVOICE],
            ['FESP', FiscalDocumentType::SPECIAL_INVOICE],
        ];
    }

    public function test_unknown_type_maps_to_other_with_warning(): void
    {
        $result = (new FelDocumentTypeMapper)->map('NUEVO');

        $this->assertSame(FiscalDocumentType::OTHER, $result['type']);
        $this->assertNotEmpty($result['warnings']);
    }
}
