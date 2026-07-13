<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Company\SwitchCompanyService;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function update(Request $request, SwitchCompanyService $switchCompany)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $company = Company::findOrFail($validated['company_id']);

        $switchCompany->handle($request->user(), $company);

        return redirect()
            ->back()
            ->with('success', 'Empresa activa cambiada correctamente.');
    }
}
