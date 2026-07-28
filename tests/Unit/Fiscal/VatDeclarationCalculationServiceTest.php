<?php

namespace Tests\Unit\Fiscal;

use App\Enums\AccountingPeriodStatus;
use App\Enums\CompanyStatus;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use App\Models\AccountingPeriod;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\Tenant;
use App\Services\Fiscal\VatDeclarationCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VatDeclarationCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_exact_decimal_cents_and_existing_document_effect_rules(): void
    {
        $tenant = Tenant::create(['name' => 'Tenant unitario', 'email' => 'unit@example.test', 'status' => 'ACTIVE']);
        $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Empresa', 'legal_name' => 'Empresa S.A.', 'tax_id' => '123', 'status' => CompanyStatus::ACTIVE]);
        $period = AccountingPeriod::create([
            'company_id' => $company->id, 'name' => 'Julio', 'start_date' => '2026-07-01',
            'end_date' => '2026-07-31', 'status' => AccountingPeriodStatus::OPEN,
        ]);
        $this->document($company, $period, FiscalDocumentType::INVOICE, '0.10');
        $this->document($company, $period, FiscalDocumentType::DEBIT_NOTE, '0.20');
        $this->document($company, $period, FiscalDocumentType::CREDIT_NOTE, '0.10');

        $result = app(VatDeclarationCalculationService::class)->calculate($company->id, $period);

        $this->assertSame('0.20', $result['totals']['sales_vat_amount']);
        $this->assertSame('0.20', $result['totals']['vat_payable']);
    }

    private function document(Company $company, AccountingPeriod $period, FiscalDocumentType $type, string $vat): void
    {
        FiscalDocument::create([
            'company_id' => $company->id,
            'accounting_period_id' => $period->id,
            'direction' => FiscalDocumentDirection::SALE,
            'document_type' => $type,
            'tax_category' => FiscalTaxCategory::GOODS,
            'document_date' => '2026-07-15',
            'third_party_name' => 'Cliente',
            'currency' => 'GTQ',
            'exchange_rate' => '1.000000',
            'taxable_amount' => '0.00',
            'exempt_amount' => '0.00',
            'non_taxable_amount' => '0.00',
            'vat_amount' => $vat,
            'other_taxes_amount' => '0.00',
            'total_amount' => $vat,
            'grants_tax_credit' => false,
            'is_small_taxpayer' => false,
            'status' => FiscalDocumentStatus::ACTIVE,
        ]);
    }
}
