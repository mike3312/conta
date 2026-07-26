<?php

namespace App\Models;

use App\Enums\FelDocumentClassification;
use App\Enums\FelDocumentDataLevel;
use App\Enums\FelDocumentSourceType;
use App\Enums\FelDocumentStatus;
use App\Enums\FelFiscalStatus;
use App\Enums\FelOperationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FelDocument extends Model
{
    protected $fillable = ['tenant_id', 'company_id', 'fel_import_batch_id', 'authorization_uuid', 'authorization_uuid_original', 'series', 'document_number', 'dte_type', 'currency', 'source_type', 'data_level', 'operation_type', 'classification', 'status', 'fiscal_status', 'issued_at', 'voided_at', 'issuer_tax_id', 'issuer_name', 'issuer_commercial_name', 'issuer_tax_regime', 'issuer_establishment_code', 'issuer_address', 'issuer_municipality', 'issuer_department', 'issuer_country', 'receiver_tax_id', 'receiver_name', 'receiver_address', 'certifier_tax_id', 'certifier_name', 'certified_at', 'subtotal', 'discount_total', 'taxable_total', 'tax_total', 'other_tax_total', 'grand_total', 'requires_tax_review', 'requires_accounting_review', 'xml_version', 'fel_version', 'signature_count', 'phrases', 'complements', 'metadata', 'xml_path', 'xml_hash', 'imported_by', 'reviewed_by', 'reviewed_at', 'observation', 'rejection_reason', 'journal_entry_id'];

    protected $casts = ['source_type' => FelDocumentSourceType::class, 'data_level' => FelDocumentDataLevel::class, 'operation_type' => FelOperationType::class, 'classification' => FelDocumentClassification::class, 'status' => FelDocumentStatus::class, 'fiscal_status' => FelFiscalStatus::class, 'issued_at' => 'datetime', 'voided_at' => 'datetime', 'certified_at' => 'datetime', 'reviewed_at' => 'datetime', 'requires_tax_review' => 'boolean', 'requires_accounting_review' => 'boolean', 'signature_count' => 'integer', 'phrases' => 'array', 'complements' => 'array', 'metadata' => 'array', 'subtotal' => 'decimal:6', 'discount_total' => 'decimal:6', 'taxable_total' => 'decimal:6', 'tax_total' => 'decimal:6', 'other_tax_total' => 'decimal:6', 'grand_total' => 'decimal:6'];

    public function scopeForActiveCompany(Builder $query): Builder
    {
        return $query->where('company_id', session('company_id'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(FelImportBatch::class, 'fel_import_batch_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(FelDocumentItem::class)->orderBy('line_number');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(FelDocumentTax::class);
    }
}
