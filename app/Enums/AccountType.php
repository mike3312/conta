<?php

namespace App\Enums;

use App\Enums\AccountNature;

enum AccountType: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case COST = 'cost';

    public function label(): string
    {
        return match ($this) {
            self::ASSET => 'Activo',
            self::LIABILITY => 'Pasivo',
            self::EQUITY => 'Capital',
            self::INCOME => 'Ingreso',
            self::EXPENSE => 'Gasto',
            self::COST => 'Costo',
        };
    }

    public function defaultNature(): AccountNature
    {
        return match ($this) {
            self::ASSET,
            self::EXPENSE,
            self::COST => AccountNature::DEBIT,

            self::LIABILITY,
            self::EQUITY,
            self::INCOME => AccountNature::CREDIT,
        };
    }
}
