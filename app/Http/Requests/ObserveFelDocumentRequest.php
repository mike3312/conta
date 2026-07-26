<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ObserveFelDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['observation' => ['required', 'string', 'max:2000']];
    }

    public function messages(): array
    {
        return ['observation.required' => 'Debe indicar la observación.'];
    }
}
