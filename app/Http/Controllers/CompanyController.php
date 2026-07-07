<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
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

public function store(StoreCompanyRequest $request): RedirectResponse
{
    $company = Company::create([
        ...$request->validated(),
        'tenant_id' => auth()->user()->tenant_id,
    ]);

    auth()->user()->companies()->attach($company->id, [
        'is_owner' => true,
        'is_active' => true,
        'joined_at' => now(),
    ]);

    return redirect()
        ->route('companies.index')
        ->with('success', 'Empresa creada correctamente.');
}
}