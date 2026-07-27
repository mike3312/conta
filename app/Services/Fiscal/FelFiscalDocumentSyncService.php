<?php

namespace App\Services\Fiscal;

use App\Enums\AccountingPeriodStatus;
use App\Enums\FelDocumentClassification;
use App\Enums\FelFiscalStatus;
use App\Enums\FelOperationType;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentSource;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalTaxCategory;
use App\Models\AccountingPeriod;
use App\Models\FelDocument;
use App\Models\FiscalDocument;
use App\Models\User;
use App\Support\FelAuthorizationNormalizer;

class FelFiscalDocumentSyncService
{
    public function __construct(
        private readonly FiscalDocumentService $documents,
        private readonly FelDocumentTypeMapper $types,
        private readonly FelAuthorizationNormalizer $authorizations,
    ) {}

    public function syncFromFel(
        FelDocument $felDocument,
        ?User $user = null,
        bool $dryRun = false,
        bool $forceUpdate = false,
    ): FiscalSyncResult {
        $felDocument->loadMissing('company');
        $company = $felDocument->company;
        if (! $company || (int) $company->id !== (int) $felDocument->company_id) {
            return $this->controlled($felDocument, 'ERROR', 'SKIPPED', errors: ['El documento FEL no tiene una empresa válida.'], dryRun: $dryRun);
        }
        if ($felDocument->tenant_id !== null && (int) $felDocument->tenant_id !== (int) $company->tenant_id) {
            return $this->controlled($felDocument, 'ERROR', 'SKIPPED', errors: ['El tenant del documento FEL no coincide con su empresa.'], dryRun: $dryRun);
        }
        if ($user && ((int) $user->tenant_id !== (int) $company->tenant_id || ! $user->companies()
            ->whereKey($company->id)->where('companies.tenant_id', $user->tenant_id)->wherePivot('is_active', true)->exists())) {
            return $this->controlled($felDocument, 'ERROR', 'SKIPPED', errors: ['El usuario no está autorizado para sincronizar esta empresa.'], dryRun: $dryRun);
        }

        $direction = match ($felDocument->operation_type) {
            FelOperationType::PURCHASE => FiscalDocumentDirection::PURCHASE,
            FelOperationType::SALE => FiscalDocumentDirection::SALE,
            FelOperationType::UNKNOWN => null,
        };
        if (! $direction) {
            return $this->controlled($felDocument, 'OBSERVED', 'OBSERVED', warnings: ['La operación FEL es desconocida y debe reclasificarse antes de incorporarla al libro fiscal.'], dryRun: $dryRun);
        }

        $uuid = $this->authorizations->normalize($felDocument->authorization_uuid);
        if (! $uuid) {
            return $this->controlled($felDocument, 'OBSERVED', 'OBSERVED', warnings: ['El documento FEL no tiene una autorización válida para sincronizar.'], dryRun: $dryRun);
        }

        $crossCompanyLink = FiscalDocument::query()->where('fel_document_id', $felDocument->id)->first();
        if ($crossCompanyLink && (int) $crossCompanyLink->company_id !== (int) $company->id) {
            return $this->controlled($felDocument, 'ERROR', 'CONFLICT', errors: ['Existe un vínculo fiscal con otra empresa. No se modificó ningún documento.'], dryRun: $dryRun);
        }

        $existing = FiscalDocument::forCompany($company->id)->where('fel_document_id', $felDocument->id)->first();
        if (! $existing) {
            $uuidMatch = $this->authorizations->whereMatches(
                FiscalDocument::forCompany($company->id),
                'authorization_uuid',
                $uuid,
            )->first();
            if ($uuidMatch) {
                return $this->controlled(
                    $felDocument,
                    'CONFLICT',
                    'CONFLICT',
                    $uuidMatch->id,
                    ['Ya existe un documento fiscal con el mismo UUID sin vínculo FEL. Debe revisarse y vincularse explícitamente.'],
                    dryRun: $dryRun,
                );
            }
        }

        if (strtoupper((string) $felDocument->currency) !== 'GTQ') {
            return $this->controlled($felDocument, 'OBSERVED', 'OBSERVED', $existing?->id, ['La moneda del FEL no es GTQ y no existe un tipo de cambio confiable.'], dryRun: $dryRun);
        }

        $thirdParty = $direction === FiscalDocumentDirection::PURCHASE
            ? ['tax_id' => $felDocument->issuer_tax_id, 'name' => $felDocument->issuer_name, 'address' => $felDocument->issuer_address]
            : ['tax_id' => $felDocument->receiver_tax_id, 'name' => $felDocument->receiver_name, 'address' => $felDocument->receiver_address];
        if (trim((string) $thirdParty['name']) === '') {
            return $this->controlled($felDocument, 'OBSERVED', 'OBSERVED', $existing?->id, ['El FEL no contiene el nombre obligatorio del tercero.'], dryRun: $dryRun);
        }

        $amounts = $this->amounts($felDocument);
        if ($amounts['error']) {
            return $this->controlled($felDocument, 'OBSERVED', 'OBSERVED', $existing?->id, [$amounts['error']], dryRun: $dryRun);
        }

        $category = $this->determineTaxCategory($felDocument);
        $warnings = [...$amounts['warnings'], ...$category['warnings']];
        $type = $this->types->map($felDocument->dte_type);
        $warnings = [...$warnings, ...$type['warnings']];

        $documentDate = $felDocument->issued_at?->toDateString();
        if (! $documentDate) {
            return $this->controlled($felDocument, 'OBSERVED', 'OBSERVED', $existing?->id, ['El FEL no contiene una fecha de emisión válida.'], dryRun: $dryRun);
        }

        $periods = AccountingPeriod::query()
            ->where('company_id', $company->id)
            ->whereDate('start_date', '<=', $documentDate)
            ->whereDate('end_date', '>=', $documentDate)
            ->get();
        if ($periods->contains(fn (AccountingPeriod $period) => $period->status === AccountingPeriodStatus::CLOSED)) {
            return $this->controlled($felDocument, 'OBSERVED', 'OBSERVED', $existing?->id, ['El documento FEL pertenece a un período contable cerrado y no fue incorporado al libro fiscal.'], dryRun: $dryRun);
        }
        $openPeriods = $periods->filter(fn (AccountingPeriod $period) => $period->status === AccountingPeriodStatus::OPEN);
        $periodId = $openPeriods->count() === 1 ? $openPeriods->first()->id : null;
        if ($openPeriods->isEmpty()) {
            $warnings[] = 'No existe un período contable abierto para la fecha del FEL; el documento quedará sin período asociado.';
        } elseif ($openPeriods->count() > 1) {
            $warnings[] = 'Existe más de un período abierto para la fecha del FEL; no se asignó un período automáticamente.';
        }

        $smallTaxpayer = $category['category'] === FiscalTaxCategory::SMALL_TAXPAYER;
        if ($smallTaxpayer && $this->cents($felDocument->tax_total) !== 0) {
            return $this->controlled($felDocument, 'CONFLICT', 'CONFLICT', $existing?->id, ['El FEL fue identificado como pequeño contribuyente, pero contiene IVA positivo.'], dryRun: $dryRun);
        }

        $stableMetadata = [
            'fel_document_id' => $felDocument->id,
            'fel_import_batch_id' => $felDocument->fel_import_batch_id,
            'imported_by' => $felDocument->imported_by,
            'mapping_version' => config('fiscal_fel.mapping_version'),
            'warnings' => array_values(array_unique($warnings)),
            'source_detail_level' => $felDocument->data_level->value,
            'source_type' => $felDocument->source_type->value,
            'values_calculated' => $amounts['calculated'],
            'tax_category_confidence' => $category['confidence'],
            'grants_tax_credit_automatic' => false,
        ];
        $attributes = [
            'fel_document_id' => $felDocument->id,
            'source' => FiscalDocumentSource::FEL,
            'source_reference' => $uuid,
            'accounting_period_id' => $periodId,
            'document_type' => $type['type'],
            'tax_category' => $category['category'],
            'document_date' => $documentDate,
            'emission_date' => $documentDate,
            'received_date' => null,
            'series' => $felDocument->series,
            'document_number' => $felDocument->document_number,
            'authorization_uuid' => $uuid,
            'third_party_tax_id' => $this->normalizeTaxId($thirdParty['tax_id']),
            'third_party_name' => trim((string) $thirdParty['name']),
            'third_party_address' => $thirdParty['address'],
            'currency' => 'GTQ',
            'exchange_rate' => '1.000000',
            ...$amounts['values'],
            'grants_tax_credit' => false,
            'is_small_taxpayer' => $smallTaxpayer,
            'status' => $felDocument->fiscal_status === FelFiscalStatus::VOIDED ? FiscalDocumentStatus::VOIDED : FiscalDocumentStatus::ACTIVE,
            'voided_at' => $felDocument->fiscal_status === FelFiscalStatus::VOIDED ? $felDocument->voided_at : null,
            'voided_by' => null,
            'void_reason' => null,
            'notes' => null,
            'created_by' => $felDocument->imported_by,
        ];

        $changed = ! $existing || $forceUpdate || $this->changed($existing, $attributes, $stableMetadata);
        $action = $existing ? ($changed ? 'UPDATED' : 'UNCHANGED') : 'CREATED';
        if ($dryRun) {
            return new FiscalSyncResult('SUCCESS', $action, $existing?->id, $warnings);
        }

        $attributes['source_metadata'] = [
            ...$stableMetadata,
            'synchronized_by' => $user?->id,
            'synchronized_at' => now()->toIso8601String(),
            'synchronization_action' => $action,
        ];
        if ($changed) {
            $existing = $this->documents->persistSynchronized($existing, $company->id, $direction, $attributes, $user);
        } elseif ($existing) {
            $existing->update(['source_metadata' => $attributes['source_metadata']]);
        }

        $result = new FiscalSyncResult('SUCCESS', $action, $existing?->id, $warnings);

        return $result;
    }

    public function determineTaxCategory(FelDocument $document): array
    {
        if ($document->classification === FelDocumentClassification::FUEL) {
            return ['category' => FiscalTaxCategory::FUEL, 'confidence' => 'SAFE', 'warnings' => []];
        }
        if (strtoupper(trim((string) $document->issuer_tax_regime)) === 'PEQ' || strtoupper($document->dte_type) === 'FPEQ') {
            return ['category' => FiscalTaxCategory::SMALL_TAXPAYER, 'confidence' => 'SAFE', 'warnings' => []];
        }
        if ($document->classification === FelDocumentClassification::LODGING) {
            return ['category' => FiscalTaxCategory::SERVICES, 'confidence' => 'SUGGESTED', 'warnings' => ['Hospedaje se clasificó provisionalmente como Servicios y requiere revisión fiscal.']];
        }

        return ['category' => FiscalTaxCategory::OTHER, 'confidence' => 'PENDING', 'warnings' => ['No fue posible determinar automáticamente si el documento corresponde a bienes o servicios; se registró como Otro.']];
    }

    private function amounts(FelDocument $document): array
    {
        $total = $this->cents($document->grand_total);
        $vat = $this->cents($document->tax_total);
        $other = $this->cents($document->other_tax_total);
        $taxable = $document->taxable_total === null ? null : $this->cents($document->taxable_total);
        $calculated = [];
        $warnings = [];

        $ivaBases = $document->taxes()
            ->where('tax_name_normalized', 'IVA')
            ->where('source_level', 'ITEM')
            ->pluck('taxable_amount');
        if ($ivaBases->isNotEmpty()) {
            $itemTaxable = $this->sumDecimals($ivaBases->all());
            if ($itemTaxable !== null && $itemTaxable !== $taxable) {
                $taxable = $itemTaxable;
                $calculated[] = 'taxable_amount';
                $warnings[] = 'La base imponible se recalculó desde las líneas de IVA para evitar duplicar bases de otros impuestos.';
            }
        }

        $small = strtoupper(trim((string) $document->issuer_tax_regime)) === 'PEQ' || strtoupper($document->dte_type) === 'FPEQ';
        if ($small && $vat === 0 && ($taxable === null || $taxable === 0)) {
            $taxable = $total - $other;
            $calculated[] = 'taxable_amount';
            $warnings[] = 'La base imponible del pequeño contribuyente se calculó desde el total porque el XML no informó una base gravable separada.';
        }

        if ($total === null || $total <= 0 || $vat === null || $other === null || $taxable === null || min($vat, $other, $taxable) < 0) {
            return ['error' => 'Los importes FEL no contienen datos suficientes y válidos para crear el documento fiscal.', 'warnings' => [], 'calculated' => [], 'values' => []];
        }
        if (abs(($taxable + $vat + $other) - $total) > 2) {
            return ['error' => 'Los importes FEL no concilian después del redondeo y no se trasladó la diferencia a montos exentos o no afectos.', 'warnings' => [], 'calculated' => [], 'values' => []];
        }

        return [
            'error' => null,
            'warnings' => $warnings,
            'calculated' => $calculated,
            'values' => [
                'taxable_amount' => $this->money($taxable),
                'exempt_amount' => '0.00',
                'non_taxable_amount' => '0.00',
                'vat_amount' => $this->money($vat),
                'other_taxes_amount' => $this->money($other),
                'total_amount' => $this->money($total),
            ],
        ];
    }

    private function cents(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if (! preg_match('/^(?<sign>-?)(?<whole>\d+)(?:\.(?<fraction>\d+))?$/', $value, $matches)) {
            return null;
        }
        $fraction = str_pad($matches['fraction'] ?? '', 3, '0');
        $cents = ((int) $matches['whole'] * 100) + (int) substr($fraction, 0, 2);
        if ((int) $fraction[2] >= 5) {
            $cents++;
        }

        return ($matches['sign'] ?? '') === '-' ? -$cents : $cents;
    }

    private function sumDecimals(array $values): ?int
    {
        $micros = 0;
        foreach ($values as $value) {
            if (! preg_match('/^(?<sign>-?)(?<whole>\d+)(?:\.(?<fraction>\d+))?$/', trim((string) $value), $matches)) {
                return null;
            }
            $fraction = str_pad(substr($matches['fraction'] ?? '', 0, 6), 6, '0');
            $amount = ((int) $matches['whole'] * 1_000_000) + (int) $fraction;
            $micros += ($matches['sign'] ?? '') === '-' ? -$amount : $amount;
        }

        $negative = $micros < 0;
        $micros = abs($micros);
        $cents = intdiv($micros, 10_000);
        if (($micros % 10_000) >= 5_000) {
            $cents++;
        }

        return $negative ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function normalizeTaxId(?string $value): ?string
    {
        $value = strtoupper(str_replace(['-', ' '], '', trim((string) $value)));

        return $value === '' ? null : $value;
    }

    private function changed(FiscalDocument $document, array $attributes, array $metadata): bool
    {
        $probe = clone $document;
        $probe->fill($attributes);
        if ($probe->isDirty(array_keys($attributes))) {
            return true;
        }

        $current = $document->source_metadata ?? [];
        foreach (['synchronized_by', 'synchronized_at', 'synchronization_action'] as $key) {
            unset($current[$key]);
        }

        return $current !== $metadata;
    }

    private function controlled(
        FelDocument $document,
        string $status,
        string $action,
        ?int $fiscalDocumentId = null,
        array $warnings = [],
        array $errors = [],
        bool $dryRun = false,
    ): FiscalSyncResult {
        $result = new FiscalSyncResult($status, $action, $fiscalDocumentId, $warnings, $errors);
        if (! $dryRun) {
            $this->recordResult($document, $result);
        }

        return $result;
    }

    private function recordResult(FelDocument $document, FiscalSyncResult $result): void
    {
        $metadata = $document->metadata ?? [];
        $metadata['fiscal_sync'] = [
            ...$result->toArray(),
            'synchronized_at' => now()->toIso8601String(),
            'mapping_version' => config('fiscal_fel.mapping_version'),
        ];
        $document->update(['metadata' => $metadata]);
    }
}
