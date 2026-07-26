<?php

namespace App\Enums;

enum FelTaxSourceLevel: string
{
    case DOCUMENT = 'DOCUMENT';
    case ITEM = 'ITEM';
    case EXCEL_SUMMARY = 'EXCEL_SUMMARY';
}
