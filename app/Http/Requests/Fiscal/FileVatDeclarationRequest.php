<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class FileVatDeclarationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'filed_at' => ['required', 'date'],
            'sat_form_number' => ['required', 'string', 'max:100'],
            'sat_access_number' => ['required', 'string', 'max:100'],
            'sat_payment_slip_number' => ['nullable', 'string', 'max:100'],
            'amount_paid' => ['required', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
