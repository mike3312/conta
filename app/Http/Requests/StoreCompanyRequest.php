<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::companyRules(includeCatalogOption: true);
    }

    public static function companyRules(?Company $company = null, bool $includeCatalogOption = false): array
    {
        $uniqueTaxId = Rule::unique('companies', 'tax_id');

        if ($company) {
            $uniqueTaxId->ignore($company->getKey());
        }

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'legal_name' => ['required', 'string', 'max:200'],
            'tax_id' => ['required', 'string', 'max:20', $uniqueTaxId],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
        ];

        if ($includeCatalogOption) {
            $rules['create_default_catalog'] = ['nullable', 'boolean'];
        }

        return $rules;
    }
}
