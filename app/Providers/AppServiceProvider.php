<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        View::composer('layouts.partials.navbar', function ($view): void {
            $user = auth()->user();
            $companies = $user
                ? $user->companies()->active()->wherePivot('is_active', true)->orderBy('name')->get()
                : collect();
            $activeCompany = $companies->first(
                fn ($company) => (string) $company->id === (string) session('company_id')
            );

            $view->with([
                'layoutCompanies' => $companies,
                'layoutActiveCompany' => $activeCompany,
            ]);
        });
    }
}
