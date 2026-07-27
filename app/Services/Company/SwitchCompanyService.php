<?php

namespace App\Services\Company;

use App\Models\Company;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

class SwitchCompanyService
{
    public function handle(User $user, Company $company): void
    {
        $canUseCompany = $user->companies()
            ->active()
            ->wherePivot('is_active', true)
            ->whereKey($company->id)
            ->exists();

        if (! $canUseCompany) {
            abort(403, 'No pertenece a una empresa activa.');
        }

        session()->put('company_id', $company->id);
        session()->forget([
            'fel_reclassification_preview',
            'fel_reclassification_preview_result',
            'fel_reclassification_result',
        ]);
        session()->migrate(true);

        $permissionRegistrar = app(PermissionRegistrar::class);
        $permissionRegistrar->setPermissionsTeamId($company->id);
        $permissionRegistrar->forgetCachedPermissions();

        $user->unsetRelation('roles')->unsetRelation('permissions');
    }
}
