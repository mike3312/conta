<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Spatie\Permission\PermissionRegistrar;

class SetCompany
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = session('company_id');

        if ($companyId) {
            app(PermissionRegistrar::class)
                ->setPermissionsTeamId($companyId);
        }

        return $next($request);
    }
}