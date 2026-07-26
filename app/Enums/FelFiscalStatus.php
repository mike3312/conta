<?php

namespace App\Enums;

enum FelFiscalStatus: string
{
    case ACTIVE = 'ACTIVE';
    case VOIDED = 'VOIDED';
    case UNKNOWN = 'UNKNOWN';
}
