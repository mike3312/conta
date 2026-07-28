<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkReviewFelDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'document_ids' => ['required', 'array', 'min:1', 'max:500'],
            'document_ids.*' => ['required', 'integer', 'distinct'],
            'action' => ['required', Rule::in(['APPROVED', 'OBSERVED', 'REJECTED'])],
            'reason' => ['nullable', 'required_if:action,OBSERVED,REJECTED', 'string', 'max:2000'],
            'filters' => ['nullable', 'array'],
            'filters.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'document_ids.required' => 'Seleccione al menos un documento visible.',
            'reason.required_if' => 'Debe indicar un motivo para observar o rechazar documentos.',
        ];
    }
}
