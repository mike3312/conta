<?php

namespace App\Enums;

enum AccountNature: string
{
    case DEBIT = 'debit';
    case CREDIT = 'credit';
}
public function label(): string
{
    return match ($this) {
        self::DEBIT => 'Deudora',
        self::CREDIT => 'Acreedora',
    };
}