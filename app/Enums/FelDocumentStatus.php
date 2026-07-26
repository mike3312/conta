<?php

namespace App\Enums;

enum FelDocumentStatus: string
{
    case PENDING = 'PENDING';
    case OBSERVED = 'OBSERVED';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';
}
