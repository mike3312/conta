<?php

namespace App\Enums;

enum AccountingPeriodStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Abierto',
            self::CLOSED => 'Cerrado',
        };
    }
}
