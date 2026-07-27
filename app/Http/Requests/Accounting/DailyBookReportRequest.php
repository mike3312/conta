<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DailyBookReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = (int) session('company_id');

        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'accounting_period_id' => [
                'nullable',
                'integer',
                Rule::exists('accounting_periods', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'number' => ['nullable', 'integer', 'min:1'],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date' => 'La fecha inicial no es válida.',
            'date_to.date' => 'La fecha final no es válida.',
            'date_to.after_or_equal' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            'accounting_period_id.integer' => 'El período contable seleccionado no es válido.',
            'accounting_period_id.exists' => 'El período contable seleccionado no pertenece a la empresa activa.',
            'number.integer' => 'El número de póliza debe ser un número entero.',
            'number.min' => 'El número de póliza debe ser mayor que cero.',
            'account_id.integer' => 'La cuenta seleccionada no es válida.',
            'account_id.exists' => 'La cuenta seleccionada no pertenece a la empresa activa.',
            'search.max' => 'El texto de búsqueda no puede exceder 255 caracteres.',
        ];
    }
}
