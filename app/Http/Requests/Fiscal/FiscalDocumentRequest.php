<?php

namespace App\Http\Requests\Fiscal;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use App\Models\FiscalDocument;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class FiscalDocumentRequest extends FormRequest
{
    abstract public function direction(): FiscalDocumentDirection;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'grants_tax_credit' => $this->direction() === FiscalDocumentDirection::PURCHASE
                ? $this->boolean('grants_tax_credit')
                : false,
            'is_small_taxpayer' => $this->boolean('is_small_taxpayer'),
        ]);
    }

    public function rules(): array
    {
        $companyId = (int) session('company_id');
        $document = $this->currentDocument();
        $uuidRule = Rule::unique('fiscal_documents', 'authorization_uuid')
            ->where(fn ($query) => $query->where('company_id', $companyId));
        if ($document) {
            $uuidRule->ignore($document->id);
        }

        return [
            'accounting_period_id' => ['nullable', 'integer', Rule::exists('accounting_periods', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'journal_entry_id' => ['nullable', 'integer', Rule::exists('journal_entries', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'document_date' => ['required', 'date'],
            'emission_date' => ['nullable', 'date'],
            'received_date' => ['nullable', 'date'],
            'document_type' => ['required', Rule::enum(FiscalDocumentType::class)],
            'tax_category' => ['required', Rule::enum(FiscalTaxCategory::class)],
            'third_party_name' => ['required', 'string', 'max:255'],
            'third_party_tax_id' => ['nullable', 'string', 'max:30'],
            'third_party_address' => ['nullable', 'string'],
            'series' => ['nullable', 'string', 'max:50'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'authorization_uuid' => ['nullable', 'string', 'max:255', $uuidRule],
            'currency' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'gt:0'],
            'taxable_amount' => ['required', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'exempt_amount' => ['required', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'non_taxable_amount' => ['required', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'vat_amount' => ['required', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'other_taxes_amount' => ['required', 'numeric', 'min:0', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'total_amount' => ['required', 'numeric', 'min:0.01', 'regex:/^\d+(?:\.\d{1,2})?$/'],
            'grants_tax_credit' => ['required', 'boolean'],
            'is_small_taxpayer' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $components = ['taxable_amount', 'exempt_amount', 'non_taxable_amount', 'vat_amount', 'other_taxes_amount'];
            $sum = array_sum(array_map(fn (string $field) => $this->toCents((string) $this->input($field)), $components));
            if (abs($sum - $this->toCents((string) $this->input('total_amount'))) > 2) {
                $validator->errors()->add('total_amount', 'El total del documento no coincide con la suma de la base imponible, montos exentos, IVA y otros impuestos.');
            }

            $smallTaxpayer = $this->input('tax_category') === FiscalTaxCategory::SMALL_TAXPAYER->value
                || $this->boolean('is_small_taxpayer');
            if ($smallTaxpayer) {
                if ($this->input('tax_category') !== FiscalTaxCategory::SMALL_TAXPAYER->value) {
                    $validator->errors()->add('tax_category', 'Los documentos de pequeño contribuyente deben usar la categoría Pequeño contribuyente.');
                }
                if ($this->toCents((string) $this->input('vat_amount')) !== 0) {
                    $validator->errors()->add('vat_amount', 'Los documentos de pequeño contribuyente deben registrar IVA cero.');
                }
                if ($this->boolean('grants_tax_credit')) {
                    $validator->errors()->add('grants_tax_credit', 'Los documentos de pequeño contribuyente no generan crédito fiscal.');
                }
            }

            if ($this->input('tax_category') === FiscalTaxCategory::EXEMPT->value
                && $this->toCents((string) $this->input('vat_amount')) !== 0) {
                $validator->errors()->add('vat_amount', 'Los documentos exentos deben registrar IVA cero.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'accounting_period_id' => 'período contable',
            'journal_entry_id' => 'póliza contable',
            'document_date' => 'fecha',
            'document_type' => 'tipo de documento',
            'tax_category' => 'categoría fiscal',
            'third_party_name' => 'nombre del tercero',
            'authorization_uuid' => 'UUID FEL',
            'taxable_amount' => 'base imponible',
            'exempt_amount' => 'monto exento',
            'non_taxable_amount' => 'monto no afecto',
            'vat_amount' => 'IVA',
            'other_taxes_amount' => 'otros impuestos',
            'total_amount' => 'total',
        ];
    }

    private function currentDocument(): ?FiscalDocument
    {
        $value = $this->route('document');

        return $value instanceof FiscalDocument
            ? $value
            : (is_numeric($value) ? FiscalDocument::forCompany((int) session('company_id'))->find((int) $value) : null);
    }

    private function toCents(string $amount): int
    {
        [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '');
        $decimal = str_pad(substr($decimal, 0, 3), 3, '0');
        $cents = ((int) $whole * 100) + (int) substr($decimal, 0, 2);

        return (int) $decimal[2] >= 5 ? $cents + 1 : $cents;
    }
}
