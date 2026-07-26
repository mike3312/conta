<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectFelDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['rejection_reason' => ['required', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return ['rejection_reason.required' => 'Debe indicar el motivo del rechazo.'];
    }
}
