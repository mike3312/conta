<?php

namespace App\Enums;

enum FelImportRowStatus: string
{
    case IMPORTED = 'IMPORTED';
    case ENRICHED = 'ENRICHED';
    case DUPLICATE = 'DUPLICATE';
    case OBSERVED = 'OBSERVED';
    case FAILED = 'FAILED';
    case IGNORED = 'IGNORED';
}
