<?php

namespace App\Enums;

enum FelOperationType: string
{
    case PURCHASE = 'PURCHASE';
    case SALE = 'SALE';
    case UNKNOWN = 'UNKNOWN';
}
