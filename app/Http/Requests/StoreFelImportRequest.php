<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFelImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && session('company_id') !== null;
    }

    public function rules(): array
    {
        $kilobytes = (int) ceil(config('fel.max_file_bytes') / 1024);

        return ['files' => ['required', 'array', 'min:1', 'max:'.config('fel.max_files')], 'files.*' => ['required', 'file', 'max:'.$kilobytes, 'extensions:xml,zip,xls,xlsx,csv', 'mimetypes:application/xml,text/xml,text/plain,application/zip,application/x-zip-compressed,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,application/csv']];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $total = collect($this->file('files', []))->sum(fn ($file) => $file?->getSize() ?? 0);
            if ($total > config('fel.max_batch_bytes')) {
                $validator->errors()->add('files', 'El lote excede el tamaño total permitido.');
            }
        }];
    }

    public function messages(): array
    {
        return ['files.required' => 'Seleccione al menos un archivo FEL.', 'files.max' => 'El lote contiene demasiados archivos.', 'files.*.extensions' => 'Solo se admiten XML, ZIP, XLS, XLSX y CSV.', 'files.*.mimetypes' => 'El tipo real de uno de los archivos no está permitido.', 'files.*.max' => 'Uno de los archivos excede el tamaño permitido.'];
    }
}
