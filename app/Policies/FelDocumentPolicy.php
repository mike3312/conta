<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\FelDocument;
use App\Models\User;

class FelDocumentPolicy
{
    public function review(User $user, FelDocument $document): bool
    {
        return (int) $document->tenant_id === (int) $user->tenant_id
            && $this->allowed($user, $document->company, 'fel.documents.review');
    }

    public function bulkReview(User $user, Company $company): bool
    {
        return $this->allowed($user, $company, 'fel.documents.bulk-review');
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
