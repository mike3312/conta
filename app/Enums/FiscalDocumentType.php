<?php

namespace App\Enums;

enum FiscalDocumentType: string
{
    case INVOICE = 'INVOICE';
    case EXCHANGE_INVOICE = 'EXCHANGE_INVOICE';
    case CREDIT_NOTE = 'CREDIT_NOTE';
    case DEBIT_NOTE = 'DEBIT_NOTE';
    case RECEIPT = 'RECEIPT';
    case SPECIAL_INVOICE = 'SPECIAL_INVOICE';
    case OTHER = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::INVOICE => 'Factura',
            self::EXCHANGE_INVOICE => 'Factura cambiaria',
            self::CREDIT_NOTE => 'Nota de crédito',
            self::DEBIT_NOTE => 'Nota de débito',
            self::RECEIPT => 'Recibo',
            self::SPECIAL_INVOICE => 'Factura especial',
            self::OTHER => 'Otro',
        };
    }
}
