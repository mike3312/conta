<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
        ]);

        $company = $request->user()
            ->companies()
            ->where('companies.id', $validated['company_id'])
            ->wherePivot('is_active', true)
            ->firstOrFail();

        session(['company_id' => $company->id]);

        return redirect()
            ->back()
            ->with('success', 'Empresa activa cambiada correctamente.');
    }
}