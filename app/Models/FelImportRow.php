<?php

namespace App\Models;

use App\Enums\FelImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FelImportRow extends Model
{
    protected $fillable = ['fel_import_batch_id', 'source_filename', 'sheet_name', 'row_number', 'authorization_uuid', 'status', 'message', 'fel_document_id', 'raw_data'];

    protected $casts = ['status' => FelImportRowStatus::class, 'row_number' => 'integer', 'raw_data' => 'array'];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(FelImportBatch::class, 'fel_import_batch_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(FelDocument::class, 'fel_document_id');
    }
}
