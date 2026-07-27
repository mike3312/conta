<?php

namespace Database\Factories;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FiscalDocumentFactory extends Factory
{
    protected $model = FiscalDocument::class;

    public function definition(): array
    {
        $taxable = fake()->numberBetween(10000, 500000);
        $vat = intdiv(($taxable * 12) + 50, 100);

        return [
            'company_id' => Company::factory(),
            'direction' => FiscalDocumentDirection::PURCHASE,
            'document_type' => FiscalDocumentType::INVOICE,
            'tax_category' => FiscalTaxCategory::GOODS,
            'document_date' => fake()->date(),
            'series' => fake()->bothify('??##'),
            'document_number' => fake()->unique()->numerify('########'),
            'authorization_uuid' => fake()->uuid(),
            'third_party_tax_id' => fake()->numerify('########'),
            'third_party_name' => fake()->company(),
            'currency' => 'GTQ',
            'exchange_rate' => '1.000000',
            'taxable_amount' => $this->money($taxable),
            'exempt_amount' => '0.00',
            'non_taxable_amount' => '0.00',
            'vat_amount' => $this->money($vat),
            'other_taxes_amount' => '0.00',
            'total_amount' => $this->money($taxable + $vat),
            'grants_tax_credit' => true,
            'is_small_taxpayer' => false,
            'status' => FiscalDocumentStatus::ACTIVE,
            'created_by' => User::factory(),
        ];
    }

    public function sale(): static
    {
        return $this->state(fn () => [
            'direction' => FiscalDocumentDirection::SALE,
            'grants_tax_credit' => false,
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn () => ['status' => FiscalDocumentStatus::VOIDED]);
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
