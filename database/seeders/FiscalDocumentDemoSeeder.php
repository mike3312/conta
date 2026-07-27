<?php

namespace Database\Seeders;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use App\Models\Company;
use App\Models\FiscalDocument;
use Illuminate\Database\Seeder;

class FiscalDocumentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first();
        $user = $company?->users()->first();
        if (! $company || ! $user) {
            $this->command?->warn('No hay una empresa con usuario asociado para crear documentos fiscales de demostración.');

            return;
        }

        $examples = [
            ['PURCHASE', 'INVOICE', 'GOODS', 'Compra de bienes', '1000.00', '120.00', '1120.00', 'ACTIVE'],
            ['PURCHASE', 'INVOICE', 'SERVICES', 'Compra de servicios', '500.00', '60.00', '560.00', 'ACTIVE'],
            ['PURCHASE', 'INVOICE', 'FUEL', 'Compra de combustible', '300.00', '36.00', '336.00', 'ACTIVE'],
            ['PURCHASE', 'INVOICE', 'SMALL_TAXPAYER', 'Pequeño contribuyente', '250.00', '0.00', '250.00', 'ACTIVE'],
            ['PURCHASE', 'CREDIT_NOTE', 'GOODS', 'Nota de crédito de compra', '100.00', '12.00', '112.00', 'ACTIVE'],
            ['SALE', 'INVOICE', 'GOODS', 'Venta gravada', '1500.00', '180.00', '1680.00', 'ACTIVE'],
            ['SALE', 'INVOICE', 'EXEMPT', 'Venta exenta', '0.00', '0.00', '400.00', 'ACTIVE'],
            ['SALE', 'CREDIT_NOTE', 'GOODS', 'Nota de crédito de venta', '200.00', '24.00', '224.00', 'ACTIVE'],
            ['SALE', 'DEBIT_NOTE', 'SERVICES', 'Nota de débito de venta', '150.00', '18.00', '168.00', 'ACTIVE'],
            ['PURCHASE', 'INVOICE', 'OTHER', 'Documento anulado', '100.00', '12.00', '112.00', 'VOIDED'],
        ];

        foreach ($examples as $index => [$direction, $type, $category, $name, $taxable, $vat, $total, $status]) {
            FiscalDocument::create([
                'company_id' => $company->id,
                'direction' => FiscalDocumentDirection::from($direction),
                'document_type' => FiscalDocumentType::from($type),
                'tax_category' => FiscalTaxCategory::from($category),
                'document_date' => now()->subDays($index),
                'document_number' => 'DEMO-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'third_party_name' => $name,
                'taxable_amount' => $taxable,
                'exempt_amount' => $category === 'EXEMPT' ? $total : '0.00',
                'non_taxable_amount' => '0.00',
                'vat_amount' => $vat,
                'other_taxes_amount' => '0.00',
                'total_amount' => $total,
                'grants_tax_credit' => $direction === 'PURCHASE' && $category !== 'SMALL_TAXPAYER',
                'is_small_taxpayer' => $category === 'SMALL_TAXPAYER',
                'status' => FiscalDocumentStatus::from($status),
                'created_by' => $user->id,
                'voided_by' => $status === 'VOIDED' ? $user->id : null,
                'voided_at' => $status === 'VOIDED' ? now() : null,
            ]);
        }
    }
}
