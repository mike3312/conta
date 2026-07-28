<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Acciones
        |--------------------------------------------------------------------------
        */

        $acciones = [
            'ver',
            'crear',
            'editar',
            'eliminar',
        ];

        /*
        |--------------------------------------------------------------------------
        | Módulos
        |--------------------------------------------------------------------------
        */

        $modulos = [
            'dashboard',

            'usuarios',
            'roles',
            'empresas',

            'clientes',
            'proveedores',

            'productos',
            'categorias',
            'marcas',

            'compras',
            'ventas',
            'cotizaciones',

            'inventario',
            'almacenes',

            'caja',
            'bancos',

            'contabilidad',
            'asientos',

            'impuestos',

            'reportes',

            'configuracion',
        ];

        foreach ($modulos as $modulo) {

            if ($modulo === 'dashboard') {
                Permission::firstOrCreate([
                    'name' => 'dashboard.ver',
                ]);

                continue;
            }

            foreach ($acciones as $accion) {

                Permission::firstOrCreate([
                    'name' => "{$modulo}.{$accion}",
                ]);

            }

        }

        $fiscalPermissions = [
            'fiscal_purchases.view', 'fiscal_purchases.create', 'fiscal_purchases.update', 'fiscal_purchases.void',
            'fiscal_sales.view', 'fiscal_sales.create', 'fiscal_sales.update', 'fiscal_sales.void',
        ];
        $workflowPermissions = [
            'fel.documents.review', 'fel.documents.bulk-review',
            'vat.declarations.view', 'vat.declarations.create', 'vat.declarations.update',
            'vat.declarations.review', 'vat.declarations.file', 'vat.declarations.export',
        ];
        foreach ([...$fiscalPermissions, ...$workflowPermissions] as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $superAdmin = Role::firstOrCreate([
            'name' => 'Super Admin',
        ]);

        $dueno = Role::firstOrCreate([
            'name' => 'Dueño',
        ]);

        $administrador = Role::firstOrCreate([
            'name' => 'Administrador',
        ]);

        $contador = Role::firstOrCreate([
            'name' => 'Contador',
        ]);

        $cajero = Role::firstOrCreate([
            'name' => 'Cajero',
        ]);

        $auditor = Role::firstOrCreate([
            'name' => 'Auditor',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Permisos
        |--------------------------------------------------------------------------
        */

        $superAdmin->givePermissionTo(Permission::all());

        $dueno->givePermissionTo(Permission::all());

        $administrador->givePermissionTo([
            'dashboard.ver',

            'usuarios.ver',
            'usuarios.crear',
            'usuarios.editar',

            'clientes.ver',
            'clientes.crear',
            'clientes.editar',
            'clientes.eliminar',

            'proveedores.ver',
            'proveedores.crear',
            'proveedores.editar',
            'proveedores.eliminar',

            'productos.ver',
            'productos.crear',
            'productos.editar',
            'productos.eliminar',
        ]);

        $contador->givePermissionTo([
            'dashboard.ver',

            'clientes.ver',

            'productos.ver',

            'compras.ver',
            'compras.crear',

            'ventas.ver',
            'ventas.crear',

            'contabilidad.ver',
            'asientos.ver',
            'asientos.crear',

            'reportes.ver',
            ...$fiscalPermissions,
        ]);

        $cajero->givePermissionTo([
            'dashboard.ver',

            'clientes.ver',

            'productos.ver',

            'ventas.ver',
            'ventas.crear',

            'caja.ver',
            'caja.crear',
        ]);

        $auditor->givePermissionTo([
            'dashboard.ver',

            'reportes.ver',

            'ventas.ver',

            'compras.ver',

            'contabilidad.ver',
            'fiscal_purchases.view',
            'fiscal_sales.view',
        ]);
    }
}
