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

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.config('fel.max_files')],
            'files.*' => [
                'required',
                'file',
                'max:'.$kilobytes,
                'extensions:xml,zip,xls,xlsx,csv',
                // Windows/fileinfo may identify XLSX as application/zip or application/octet-stream.
                'mimetypes:application/xml,text/xml,text/plain,application/octet-stream,application/zip,application/x-zip-compressed,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,application/csv',
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $total = collect($this->file('files', []))->sum(fn ($file) => $file?->getSize() ?? 0);
            if ($total > config('fel.max_batch_bytes')) {
                $validator->errors()->add('files', '[FEL-UPLOAD-VALIDATION] El lote excede el tamaño total permitido.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'files.required' => '[FEL-UPLOAD-VALIDATION] Seleccione al menos un archivo FEL.',
            'files.max' => '[FEL-UPLOAD-VALIDATION] El lote contiene demasiados archivos.',
            'files.*.extensions' => '[FEL-UPLOAD-VALIDATION] Solo se admiten XML, ZIP, XLS, XLSX y CSV.',
            'files.*.mimetypes' => '[FEL-MIME] El tipo real de uno de los archivos no está permitido.',
            'files.*.max' => '[FEL-UPLOAD-VALIDATION] Uno de los archivos excede el tamaño permitido.',
        ];
    }
}
