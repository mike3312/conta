<?php

namespace App\Models;

use App\Enums\FelTaxSourceLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FelDocumentTax extends Model
{
    protected $fillable = ['fel_document_id', 'fel_document_item_id', 'tax_name', 'tax_name_normalized', 'tax_code', 'taxable_unit_code', 'taxable_amount', 'tax_amount', 'source_level', 'raw_data'];

    protected $casts = ['source_level' => FelTaxSourceLevel::class, 'taxable_amount' => 'decimal:6', 'tax_amount' => 'decimal:6', 'raw_data' => 'array'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(FelDocument::class, 'fel_document_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FelDocumentItem::class, 'fel_document_item_id');
    }
}
