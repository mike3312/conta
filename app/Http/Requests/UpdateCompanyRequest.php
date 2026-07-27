<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');
        $user = $this->user();

        return $company instanceof Company
            && $user !== null
            && (int) $company->tenant_id === (int) $user->tenant_id
            && $user->companies()
                ->whereKey($company->getKey())
                ->wherePivot('is_active', true)
                ->exists();
    }

    public function rules(): array
    {
        /** @var Company $company */
        $company = $this->route('company');

        return StoreCompanyRequest::companyRules($company);
    }
}
