<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVatDeclarationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'accounting_period_id' => [
                'required', 'integer',
                Rule::exists('accounting_periods', 'id')->where(fn ($query) => $query->where('company_id', session('company_id'))),
            ],
            'previous_credit_balance' => ['nullable', 'decimal:0,2', 'min:0'],
            'vat_withholdings' => ['nullable', 'decimal:0,2', 'min:0'],
            'vat_perceptions' => ['nullable', 'decimal:0,2', 'min:0'],
            'manual_debit_adjustments' => ['nullable', 'decimal:0,2', 'min:0'],
            'manual_credit_adjustments' => ['nullable', 'decimal:0,2', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
