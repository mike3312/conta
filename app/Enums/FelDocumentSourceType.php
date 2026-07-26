<?php

namespace App\Enums;

enum FelDocumentSourceType: string
{
    case XML = 'XML';
    case ZIP_XML = 'ZIP_XML';
    case EXCEL = 'EXCEL';
    case CSV = 'CSV';
}
