<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetCompany
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $activeCompanies = $user->companies()
            ->active()
            ->wherePivot('is_active', true)
            ->orderBy('companies.id')
            ->get(['companies.id']);

        $companyId = session('company_id');
        $isValid = $companyId !== null && $activeCompanies->contains(
            fn ($company) => (string) $company->id === (string) $companyId
        );

        if (! $isValid) {
            $companyId = $activeCompanies->first()?->id;

            if ($companyId === null) {
                session()->forget('company_id');
            } else {
                session()->put('company_id', $companyId);
            }
        }

        app(PermissionRegistrar::class)->setPermissionsTeamId($companyId);

        return $next($request);
    }
}
