<?php

namespace App\Enums;

enum FiscalTaxCategory: string
{
    case GOODS = 'GOODS';
    case SERVICES = 'SERVICES';
    case FUEL = 'FUEL';
    case SMALL_TAXPAYER = 'SMALL_TAXPAYER';
    case EXEMPT = 'EXEMPT';
    case IMPORT = 'IMPORT';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::GOODS => 'Bienes',
            self::SERVICES => 'Servicios',
            self::FUEL => 'Combustible',
            self::SMALL_TAXPAYER => 'Pequeño contribuyente',
            self::EXEMPT => 'Exento',
            self::IMPORT => 'Importación',
            self::OTHER => 'Otro',
        };
    }
}
