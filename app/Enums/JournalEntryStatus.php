<?php

namespace App\Enums;

enum JournalEntryStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case VOIDED = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::POSTED => 'Contabilizada',
            self::VOIDED => 'Anulada',
        };
    }
}
