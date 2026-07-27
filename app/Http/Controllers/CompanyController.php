<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Models\FelDocument;
use App\Services\Accounting\AccountCatalogService;
use App\Services\Company\SwitchCompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();
        $companies = $user->companies()
            ->where('companies.tenant_id', $user->tenant_id)
            ->wherePivot('is_active', true)
            ->orderByDesc('companies.created_at')
            ->get();

        return view('companies.index', compact('companies'));
    }

    public function create(): View
    {
        return view('companies.create');
    }

    public function edit(Company $company): View
    {
        $this->authorizeCompanyAccess($company);

        return view('companies.edit', compact('company'));
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $this->authorizeCompanyAccess($company);
        $taxIdChanged = $company->tax_id !== $request->validated('tax_id');
        $company->update($request->validated());

        $redirect = redirect()
            ->route('companies.index')
            ->with('success', 'Empresa actualizada correctamente.');

        if ($taxIdChanged && FelDocument::where('company_id', $company->id)->exists()) {
            $redirect->with('fel_reclassification_recommended', [
                'company_id' => $company->id,
                'company_name' => $company->name,
            ]);
        }

        return $redirect;
    }

    public function store(
        StoreCompanyRequest $request,
        AccountCatalogService $accountCatalog,
        SwitchCompanyService $switchCompany,
    ): RedirectResponse {
        $user = $request->user();
        $validated = $request->safe()->except('create_default_catalog');

        $company = DB::transaction(function () use ($validated, $request, $user, $accountCatalog) {
            $company = Company::create([
                ...$validated,
                'tenant_id' => $user->tenant_id,
            ]);

            $user->companies()->attach($company->id, [
                'is_owner' => true,
                'is_active' => true,
                'joined_at' => now(),
            ]);

            if ($request->boolean('create_default_catalog')) {
                $accountCatalog->createDefaultCatalog($company);
            }

            return $company;
        });

        // Activate only after the transaction has committed successfully.
        $switchCompany->handle($user, $company);

        return redirect()
            ->route('accounts.index')
            ->with('success', 'Empresa creada correctamente.');
    }

    private function authorizeCompanyAccess(Company $company): void
    {
        $user = auth()->user();
        $hasAccess = $user
            && (int) $company->tenant_id === (int) $user->tenant_id
            && $user->companies()
                ->whereKey($company->getKey())
                ->wherePivot('is_active', true)
                ->exists();

        abort_unless($hasAccess, 403);
    }
}
