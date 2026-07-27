<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentType;

class FelDocumentTypeMapper
{
    public function map(?string $dteType): array
    {
        $code = strtoupper(trim((string) $dteType));
        $mapped = config("fiscal_fel.document_types.{$code}");

        if ($mapped && ($type = FiscalDocumentType::tryFrom($mapped))) {
            return ['type' => $type, 'warnings' => []];
        }

        return [
            'type' => FiscalDocumentType::OTHER,
            'warnings' => ["El tipo DTE {$code} no tiene equivalencia fiscal específica y se registró como Otro."],
        ];
    }
}
