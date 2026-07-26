<?php

namespace App\Enums;

enum FelImportBatchStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case COMPLETED_WITH_ERRORS = 'COMPLETED_WITH_ERRORS';
    case FAILED = 'FAILED';
}
