<?php

namespace App\Enums;

enum FiscalDocumentStatus: string
{
    case ACTIVE = 'ACTIVE';
    case VOIDED = 'VOIDED';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activo',
            self::VOIDED => 'Anulado',
        };
    }
}
