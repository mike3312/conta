<?php

namespace App\Enums;

enum FelDocumentClassification: string
{
    case GENERAL_PURCHASE = 'GENERAL_PURCHASE';
    case GENERAL_SALE = 'GENERAL_SALE';
    case FUEL = 'FUEL';
    case LODGING = 'LODGING';
    case UNCLASSIFIED = 'UNCLASSIFIED';
}
