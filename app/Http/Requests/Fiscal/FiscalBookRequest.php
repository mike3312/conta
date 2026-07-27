<?php

namespace App\Http\Requests\Fiscal;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FiscalBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $companyId = (int) session('company_id');

        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'accounting_period_id' => ['nullable', 'integer', Rule::exists('accounting_periods', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'document_type' => ['nullable', Rule::enum(FiscalDocumentType::class)],
            'tax_category' => ['nullable', Rule::enum(FiscalTaxCategory::class)],
            'status' => ['nullable', Rule::enum(FiscalDocumentStatus::class)],
            'third_party_tax_id' => ['nullable', 'string', 'max:30'],
            'third_party_name' => ['nullable', 'string', 'max:255'],
            'series' => ['nullable', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'authorization_uuid' => ['nullable', 'string', 'max:255'],
            'grants_tax_credit' => ['nullable', 'boolean'],
            'is_small_taxpayer' => ['nullable', 'boolean'],
        ];
    }
}
