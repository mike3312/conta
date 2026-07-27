<?php

namespace Tests\Unit\Fiscal;

use App\Enums\FiscalDocumentType;
use App\Services\Fiscal\FiscalDocumentService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FiscalDocumentServiceTest extends TestCase
{
    #[DataProvider('documentSigns')]
    public function test_document_type_has_the_expected_effective_sign(FiscalDocumentType $type, int $expectedSign): void
    {
        $service = new FiscalDocumentService;

        $this->assertSame($expectedSign, $service->effectiveSign($type));
    }

    public static function documentSigns(): array
    {
        return [
            'invoice adds' => [FiscalDocumentType::INVOICE, 1],
            'exchange invoice adds' => [FiscalDocumentType::EXCHANGE_INVOICE, 1],
            'credit note subtracts' => [FiscalDocumentType::CREDIT_NOTE, -1],
            'debit note adds' => [FiscalDocumentType::DEBIT_NOTE, 1],
            'receipt adds' => [FiscalDocumentType::RECEIPT, 1],
            'special invoice adds' => [FiscalDocumentType::SPECIAL_INVOICE, 1],
            'other document adds' => [FiscalDocumentType::OTHER, 1],
        ];
    }
}
