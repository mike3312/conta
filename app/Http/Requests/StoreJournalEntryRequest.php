<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accounting_period_id' => ['required', 'integer'],
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'integer'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'gte:0', 'decimal:0,2', 'max:9999999999999999.99'],
            'lines.*.credit' => ['required', 'numeric', 'gte:0', 'decimal:0,2', 'max:9999999999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'accounting_period_id.required' => 'Debes seleccionar un período contable.',
            'accounting_period_id.integer' => 'El período contable seleccionado no es válido.',
            'entry_date.required' => 'La fecha de la póliza es obligatoria.',
            'entry_date.date' => 'La fecha de la póliza no es válida.',
            'description.required' => 'La descripción de la póliza es obligatoria.',
            'description.string' => 'La descripción de la póliza no es válida.',
            'description.max' => 'La descripción no puede exceder 255 caracteres.',
            'reference.string' => 'La referencia no es válida.',
            'reference.max' => 'La referencia no puede exceder 255 caracteres.',
            'lines.required' => 'Debes agregar al menos una línea contable.',
            'lines.array' => 'Las líneas contables no tienen un formato válido.',
            'lines.min' => 'Debes agregar al menos una línea contable.',
            'lines.*.account_id.required' => 'Debes seleccionar una cuenta en cada línea.',
            'lines.*.account_id.integer' => 'La cuenta seleccionada no es válida.',
            'lines.*.description.string' => 'La descripción de la línea no es válida.',
            'lines.*.description.max' => 'La descripción de una línea no puede exceder 255 caracteres.',
            'lines.*.debit.required' => 'El importe del Debe es obligatorio.',
            'lines.*.credit.required' => 'El importe del Haber es obligatorio.',
            'lines.*.debit.numeric' => 'El importe del Debe debe ser numérico.',
            'lines.*.credit.numeric' => 'El importe del Haber debe ser numérico.',
            'lines.*.debit.gte' => 'El importe del Debe no puede ser negativo.',
            'lines.*.credit.gte' => 'El importe del Haber no puede ser negativo.',
            'lines.*.debit.decimal' => 'El importe del Debe debe tener como máximo dos decimales.',
            'lines.*.credit.decimal' => 'El importe del Haber debe tener como máximo dos decimales.',
            'lines.*.debit.max' => 'El importe del Debe excede el máximo permitido.',
            'lines.*.credit.max' => 'El importe del Haber excede el máximo permitido.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('lines', []) as $index => $line) {
                    $debit = (string) ($line['debit'] ?? '0');
                    $credit = (string) ($line['credit'] ?? '0');

                    if ($this->isPositiveAmount($debit) && $this->isPositiveAmount($credit)) {
                        $validator->errors()->add(
                            "lines.$index.debit",
                            'Una línea no puede tener importes positivos en Debe y Haber al mismo tiempo.'
                        );
                    }
                }
            },
        ];
    }

    private function isPositiveAmount(string $amount): bool
    {
        $amount = trim($amount);

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            return false;
        }

        return trim(str_replace('.', '', $amount), '0') !== '';
    }
}
