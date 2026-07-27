<?php

namespace App\Enums;

enum FiscalDocumentSource: string
{
    case MANUAL = 'MANUAL';
    case FEL = 'FEL';
    case CSV = 'CSV';
    case XML = 'XML';
    case API = 'API';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::MANUAL => 'Registro manual',
            self::FEL => 'Documento FEL',
            self::CSV => 'Importación CSV',
            self::XML => 'Importación XML',
            self::API => 'Integración externa',
            self::OTHER => 'Otro origen',
        };
    }
}
