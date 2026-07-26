<?php

namespace App\Services\Fel;

use App\Enums\FelDocumentClassification;
use App\Enums\FelOperationType;

class FelClassificationService
{
    public function classify(array $data, ?string $companyTaxId): array
    {
        $company = $this->taxId($companyTaxId);
        $issuer = $this->taxId(data_get($data, 'issuer.tax_id'));
        $receiver = $this->taxId(data_get($data, 'receiver.tax_id'));
        $operation = ! $company ? FelOperationType::UNKNOWN : ($company === $receiver ? FelOperationType::PURCHASE : ($company === $issuer ? FelOperationType::SALE : FelOperationType::UNKNOWN));
        $classification = match ($operation) {
            FelOperationType::PURCHASE => FelDocumentClassification::GENERAL_PURCHASE, FelOperationType::SALE => FelDocumentClassification::GENERAL_SALE, default => FelDocumentClassification::UNCLASSIFIED
        };
        $signals = collect($data['taxes'] ?? [])->pluck('tax_name')->merge(collect($data['items'] ?? [])->pluck('description'))->merge(collect($data['complements'] ?? [])->flatten()->filter(fn ($value) => is_string($value)))->map(fn ($value) => $this->text((string) $value));
        $taxNames = collect($data['taxes'] ?? [])->pluck('tax_name')->map(fn ($value) => $this->text((string) $value));
        $fuel = $taxNames->contains(fn ($name) => str_contains($name, 'PETROLEO') || preg_match('/\bIDP\b/', $name)) || $signals->contains(fn ($text) => preg_match('/\b(GASOLINA|DIESEL|COMBUSTIBLE|GASOHOL|KEROSENO|BUNKER|GALON)\b/', $text));
        $lodging = $taxNames->contains(fn ($name) => str_contains($name, 'TURISMO HOSPEDAJE'));
        $specialTax = $taxNames->contains(fn ($name) => $name !== '' && $name !== 'IVA');
        if ($fuel) {
            $classification = FelDocumentClassification::FUEL;
        } elseif ($lodging) {
            $classification = FelDocumentClassification::LODGING;
        }

        return ['operation_type' => $operation, 'classification' => $classification, 'requires_tax_review' => $fuel || $lodging || $specialTax, 'missing_company_tax_id' => ! $company];
    }

    public function normalizeTaxName(string $value): string
    {
        return mb_substr($this->text($value), 0, 100);
    }

    private function taxId(?string $value): ?string
    {
        $value = strtoupper(preg_replace('/[\s-]+/', '', (string) $value));

        return $value === '' || $value === 'CF' ? null : $value;
    }

    private function text(string $value): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value)));
    }
}
