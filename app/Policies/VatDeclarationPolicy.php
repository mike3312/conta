<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Models\VatDeclaration;

class VatDeclarationPolicy
{
    public function viewAny(User $user, Company $company): bool
    {
        return $this->allowed($user, $company, 'vat.declarations.view');
    }

    public function create(User $user, Company $company): bool
    {
        return $this->allowed($user, $company, 'vat.declarations.create');
    }

    public function view(User $user, VatDeclaration $declaration): bool
    {
        return $this->allowed($user, $declaration->company, 'vat.declarations.view');
    }

    public function update(User $user, VatDeclaration $declaration): bool
    {
        return $this->allowed($user, $declaration->company, 'vat.declarations.update');
    }

    public function review(User $user, VatDeclaration $declaration): bool
    {
        return $this->allowed($user, $declaration->company, 'vat.declarations.review');
    }

    public function file(User $user, VatDeclaration $declaration): bool
    {
        return $this->allowed($user, $declaration->company, 'vat.declarations.file');
    }

    public function export(User $user, VatDeclaration $declaration): bool
    {
        return $this->allowed($user, $declaration->company, 'vat.declarations.export');
    }

    private function allowed(User $user, Company $company, string $permission): bool
    {
        if ((int) $user->tenant_id !== (int) $company->tenant_id) {
            return false;
        }

        $membership = $user->companies()->whereKey($company->id)->wherePivot('is_active', true)->first();

        return $membership !== null
            && ((bool) $membership->pivot->is_owner || $user->can($permission));
    }
}
