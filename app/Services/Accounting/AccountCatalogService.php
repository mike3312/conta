<?php
namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\Company;

class AccountCatalogService
{
    public function createDefaultCatalog(Company $company): void
    {
        $accounts = [
            ['code' => '1', 'name' => 'Activo', 'type' => AccountType::ASSET, 'parent' => null, 'entries' => false],
            ['code' => '1.1', 'name' => 'Activo Corriente', 'type' => AccountType::ASSET, 'parent' => '1', 'entries' => false],
            ['code' => '1.1.01', 'name' => 'Caja General', 'type' => AccountType::ASSET, 'parent' => '1.1', 'entries' => true],
            ['code' => '1.1.02', 'name' => 'Bancos', 'type' => AccountType::ASSET, 'parent' => '1.1', 'entries' => true],
            ['code' => '1.1.03', 'name' => 'Clientes', 'type' => AccountType::ASSET, 'parent' => '1.1', 'entries' => true],
            ['code' => '1.1.04', 'name' => 'Inventarios', 'type' => AccountType::ASSET, 'parent' => '1.1', 'entries' => true],

            ['code' => '2', 'name' => 'Pasivo', 'type' => AccountType::LIABILITY, 'parent' => null, 'entries' => false],
            ['code' => '2.1', 'name' => 'Pasivo Corriente', 'type' => AccountType::LIABILITY, 'parent' => '2', 'entries' => false],
            ['code' => '2.1.01', 'name' => 'Proveedores', 'type' => AccountType::LIABILITY, 'parent' => '2.1', 'entries' => true],
            ['code' => '2.1.02', 'name' => 'Impuestos por Pagar', 'type' => AccountType::LIABILITY, 'parent' => '2.1', 'entries' => true],

            ['code' => '3', 'name' => 'Capital', 'type' => AccountType::EQUITY, 'parent' => null, 'entries' => false],
            ['code' => '3.1', 'name' => 'Capital Contable', 'type' => AccountType::EQUITY, 'parent' => '3', 'entries' => false],
            ['code' => '3.1.01', 'name' => 'Capital Social', 'type' => AccountType::EQUITY, 'parent' => '3.1', 'entries' => true],
            ['code' => '3.1.02', 'name' => 'Utilidades Retenidas', 'type' => AccountType::EQUITY, 'parent' => '3.1', 'entries' => true],

            ['code' => '4', 'name' => 'Ingresos', 'type' => AccountType::INCOME, 'parent' => null, 'entries' => false],
            ['code' => '4.1', 'name' => 'Ingresos Ordinarios', 'type' => AccountType::INCOME, 'parent' => '4', 'entries' => false],
            ['code' => '4.1.01', 'name' => 'Ventas', 'type' => AccountType::INCOME, 'parent' => '4.1', 'entries' => true],

            ['code' => '5', 'name' => 'Gastos', 'type' => AccountType::EXPENSE, 'parent' => null, 'entries' => false],
            ['code' => '5.1', 'name' => 'Gastos de Administración', 'type' => AccountType::EXPENSE, 'parent' => '5', 'entries' => false],
            ['code' => '5.1.01', 'name' => 'Sueldos y Salarios', 'type' => AccountType::EXPENSE, 'parent' => '5.1', 'entries' => true],
            ['code' => '5.1.02', 'name' => 'Alquileres', 'type' => AccountType::EXPENSE, 'parent' => '5.1', 'entries' => true],
            ['code' => '5.1.03', 'name' => 'Servicios Básicos', 'type' => AccountType::EXPENSE, 'parent' => '5.1', 'entries' => true],

            ['code' => '6', 'name' => 'Costos', 'type' => AccountType::COST, 'parent' => null, 'entries' => false],
            ['code' => '6.1', 'name' => 'Costo de Ventas', 'type' => AccountType::COST, 'parent' => '6', 'entries' => false],
            ['code' => '6.1.01', 'name' => 'Costo de Mercadería Vendida', 'type' => AccountType::COST, 'parent' => '6.1', 'entries' => true],
        ];

        $created = [];

        foreach ($accounts as $item) {
            $parent = $item['parent'] ? ($created[$item['parent']] ?? null) : null;

            $created[$item['code']] = Account::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'code' => $item['code'],
                ],
                [
                    'parent_id' => $parent?->id,
                    'name' => $item['name'],
                    'account_type' => $item['type']->value,
                    'nature' => $item['type']->defaultNature()->value,
                    'allows_entries' => $item['entries'],
                    'level' => $parent ? $parent->level + 1 : 1,
                    'is_active' => true,
                ]
            );
        }
    }
}