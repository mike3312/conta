<?php

namespace App\Models;

use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatDeclarationDocument extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'direction' => FiscalDocumentDirection::class,
        'document_type' => FiscalDocumentType::class,
        'fiscal_status' => FiscalDocumentStatus::class,
        'included' => 'boolean',
        'effect' => 'integer',
        'source_snapshot' => 'array',
        'taxable_amount' => 'decimal:2',
        'exempt_amount' => 'decimal:2',
        'non_taxable_amount' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'creditable_vat_amount' => 'decimal:2',
        'non_creditable_vat_amount' => 'decimal:2',
        'other_taxes_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(VatDeclaration::class, 'vat_declaration_id');
    }

    public function fiscalDocument(): BelongsTo
    {
        return $this->belongsTo(FiscalDocument::class);
    }
}
