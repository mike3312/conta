<?php

namespace App\Enums;

enum AccountType: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';
    case INCOME = 'income';
    case EXPENSE = 'expense';
    case COST = 'cost';
}
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