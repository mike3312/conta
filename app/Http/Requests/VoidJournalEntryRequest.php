<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'void_reason.required' => 'El motivo de anulación es obligatorio.',
            'void_reason.string' => 'El motivo de anulación no es válido.',
            'void_reason.max' => 'El motivo de anulación no puede exceder 1000 caracteres.',
        ];
    }
}
