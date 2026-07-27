<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Company\SwitchCompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CompanySwitchController extends Controller
{
    public function update(Request $request, SwitchCompanyService $switchCompany): RedirectResponse
    {
        $redirectRoute = $request->input('redirect_context') === 'fel'
            ? 'fel-documents.index'
            : 'dashboard';
        $validator = Validator::make($request->all(), [
            'company_id' => ['required', 'exists:companies,id'],
        ]);
        if ($validator->fails()) {
            return redirect()->route($redirectRoute)->withErrors($validator);
        }

        $validated = $validator->validated();

        $company = Company::findOrFail($validated['company_id']);

        $switchCompany->handle($request->user(), $company);

        return redirect()
            ->route($redirectRoute)
            ->with('success', 'Empresa activa cambiada correctamente.');
    }
}
