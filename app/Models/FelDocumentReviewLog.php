<?php

namespace App\Models;

use App\Enums\FelDocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FelDocumentReviewLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'previous_status' => FelDocumentStatus::class,
        'new_status' => FelDocumentStatus::class,
        'performed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function felDocument(): BelongsTo
    {
        return $this->belongsTo(FelDocument::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
