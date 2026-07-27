<?php

namespace App\Enums;

enum FiscalDocumentDirection: string
{
    case PURCHASE = 'PURCHASE';
    case SALE = 'SALE';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Compra',
            self::SALE => 'Venta',
        };
    }
}
