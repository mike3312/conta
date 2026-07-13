<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use App\Services\Accounting\AccountCatalogService;
use App\Services\Company\SwitchCompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        $companies = Company::where('tenant_id', auth()->user()->tenant_id)
            ->latest()
            ->get();

        return view('companies.index', compact('companies'));
    }

    public function create(): View
    {
        return view('companies.create');
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
}
