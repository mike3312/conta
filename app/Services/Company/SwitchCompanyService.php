<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Session;
use Spatie\Permission\PermissionRegistrar;

class SwitchCompanyService
{
    public function handle(User $user, Company $company): void
    {
        // Verificar que el usuario pertenece a la empresa
        if (! $user->companies()->whereKey($company->id)->exists()) {
            abort(403, 'No pertenece a esta empresa.');
        }

        // Guardar empresa activa en la sesión
        Session::put('company_id', $company->id);

        // Configurar Spatie Teams
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        // Limpiar caché de permisos
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}