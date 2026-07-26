<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FelDocumentItem extends Model
{
    protected $fillable = ['fel_document_id', 'line_number', 'goods_or_service', 'quantity', 'description', 'unit_price', 'gross_price', 'discount', 'other_discount', 'taxable_amount', 'tax_amount', 'total', 'classification', 'raw_data'];

    protected $casts = ['line_number' => 'integer', 'raw_data' => 'array', 'quantity' => 'decimal:6', 'unit_price' => 'decimal:6', 'gross_price' => 'decimal:6', 'discount' => 'decimal:6', 'other_discount' => 'decimal:6', 'taxable_amount' => 'decimal:6', 'tax_amount' => 'decimal:6', 'total' => 'decimal:6'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(FelDocument::class, 'fel_document_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(FelDocumentTax::class);
    }
}
