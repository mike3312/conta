<?php

namespace App\Models;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentSource;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\FiscalTaxCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalDocument extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'fel_document_id', 'source', 'source_reference', 'source_metadata',
        'accounting_period_id', 'journal_entry_id', 'direction', 'document_type',
        'tax_category', 'document_date', 'emission_date', 'received_date', 'series', 'document_number',
        'authorization_uuid', 'third_party_tax_id', 'third_party_name', 'third_party_address', 'currency',
        'exchange_rate', 'taxable_amount', 'exempt_amount', 'non_taxable_amount', 'vat_amount',
        'other_taxes_amount', 'total_amount', 'grants_tax_credit', 'is_small_taxpayer', 'status', 'notes',
        'created_by', 'updated_by', 'voided_by', 'voided_at', 'void_reason',
    ];

    protected $casts = [
        'direction' => FiscalDocumentDirection::class,
        'document_type' => FiscalDocumentType::class,
        'tax_category' => FiscalTaxCategory::class,
        'status' => FiscalDocumentStatus::class,
        'source' => FiscalDocumentSource::class,
        'source_metadata' => 'array',
        'document_date' => 'date',
        'emission_date' => 'date',
        'received_date' => 'date',
        'voided_at' => 'datetime',
        'grants_tax_credit' => 'boolean',
        'is_small_taxpayer' => 'boolean',
        'exchange_rate' => 'decimal:6',
        'taxable_amount' => 'decimal:2',
        'exempt_amount' => 'decimal:2',
        'non_taxable_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'other_taxes_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopePurchases(Builder $query): Builder
    {
        return $query->where('direction', FiscalDocumentDirection::PURCHASE->value);
    }

    public function scopeSales(Builder $query): Builder
    {
        return $query->where('direction', FiscalDocumentDirection::SALE->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FiscalDocumentStatus::ACTIVE->value);
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->where('status', FiscalDocumentStatus::VOIDED->value);
    }

    public function scopeBetweenDates(Builder $query, ?string $startDate, ?string $endDate): Builder
    {
        return $query->when($startDate, fn (Builder $query, string $date) => $query->whereDate('document_date', '>=', $date))
            ->when($endDate, fn (Builder $query, string $date) => $query->whereDate('document_date', '<=', $date));
    }

    public function scopeForPeriod(Builder $query, ?int $periodId): Builder
    {
        return $query->when($periodId, fn (Builder $query, int $id) => $query->where('accounting_period_id', $id));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $query, string $value) {
            $query->where(function (Builder $query) use ($value) {
                $query->where('third_party_name', 'like', "%{$value}%")
                    ->orWhere('third_party_tax_id', 'like', "%{$value}%")
                    ->orWhere('document_number', 'like', "%{$value}%")
                    ->orWhere('authorization_uuid', 'like', "%{$value}%");
            });
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function felDocument(): BelongsTo
    {
        return $this->belongsTo(FelDocument::class);
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
