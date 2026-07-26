<?php

namespace App\Models;

use App\Enums\FelDocumentSourceType;
use App\Enums\FelImportBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FelImportBatch extends Model
{
    protected $fillable = ['tenant_id', 'company_id', 'source_type', 'original_filename', 'stored_path', 'original_mime_type', 'original_size', 'total_files', 'total_records', 'successful_records', 'enriched_records', 'duplicate_records', 'observed_records', 'failed_records', 'fuel_records', 'voided_records', 'status', 'imported_by', 'started_at', 'finished_at'];

    protected $casts = [
        'source_type' => FelDocumentSourceType::class,
        'status' => FelImportBatchStatus::class,
        'original_size' => 'integer',
        'total_files' => 'integer',
        'total_records' => 'integer',
        'successful_records' => 'integer',
        'enriched_records' => 'integer',
        'duplicate_records' => 'integer',
        'observed_records' => 'integer',
        'failed_records' => 'integer',
        'fuel_records' => 'integer',
        'voided_records' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(FelImportRow::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FelDocument::class);
    }
}
