<?php

namespace Tests\Unit\Fel;

use App\Enums\FelDocumentClassification;
use App\Enums\FelOperationType;
use App\Services\Fel\FelClassificationService;
use PHPUnit\Framework\TestCase;

class FelClassificationServiceTest extends TestCase
{
    public function test_it_classifies_purchase_sale_fuel_and_special_taxes(): void
    {
        $service = new FelClassificationService;
        $purchase = $service->classify(['issuer' => ['tax_id' => '1'], 'receiver' => ['tax_id' => '123-4'], 'items' => [['description' => 'Aceite lubricante']], 'taxes' => []], '1234');
        $this->assertSame(FelOperationType::PURCHASE, $purchase['operation_type']);
        $this->assertSame(FelDocumentClassification::GENERAL_PURCHASE, $purchase['classification']);
        $fuel = $service->classify(['issuer' => ['tax_id' => '1234'], 'receiver' => ['tax_id' => 'CF'], 'items' => [['description' => 'Gasolina superior']]], '1234');
        $this->assertSame(FelOperationType::SALE, $fuel['operation_type']);
        $this->assertSame(FelDocumentClassification::FUEL, $fuel['classification']);
        $this->assertTrue($fuel['requires_tax_review']);
        $lodging = $service->classify(['issuer' => [], 'receiver' => [], 'taxes' => [['tax_name' => 'Turismo Hospedaje']]], null);
        $this->assertSame(FelOperationType::UNKNOWN, $lodging['operation_type']);
        $this->assertSame(FelDocumentClassification::LODGING, $lodging['classification']);
    }
}
