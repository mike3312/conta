<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
    return [
        'name' => ['required', 'string', 'max:150'],
        'legal_name' => ['required', 'string', 'max:200'],
        'tax_id' => ['required', 'string', 'max:20', 'unique:companies,tax_id'],
        'email' => ['nullable', 'email'],
        'phone' => ['nullable', 'string', 'max:30'],
        'address' => ['nullable', 'string'],
        'city' => ['nullable', 'string', 'max:100'],
        'state' => ['nullable', 'string', 'max:100'],
    ];
}
}
