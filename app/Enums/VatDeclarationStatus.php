<?php

namespace App\Enums;

enum VatDeclarationStatus: string
{
    case DRAFT = 'DRAFT';
    case REVIEWED = 'REVIEWED';
    case READY_TO_FILE = 'READY_TO_FILE';
    case FILED = 'FILED';
    case AMENDED = 'AMENDED';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Borrador',
            self::REVIEWED => 'Revisada',
            self::READY_TO_FILE => 'Lista para presentar',
            self::FILED => 'Presentada',
            self::AMENDED => 'Rectificada',
        };
    }
}
