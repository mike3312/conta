<?php

namespace App\Models;

use App\Enums\JournalEntryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    protected $fillable = [
        'company_id',
        'accounting_period_id',
        'number',
        'entry_date',
        'description',
        'reference',
        'status',
        'created_by',
        'updated_by',
        'posted_by',
        'posted_at',
        'voided_by',
        'voided_at',
        'void_reason',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'status' => JournalEntryStatus::class,
        'posted_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class)->orderBy('line_order');
    }

    public function fiscalDocuments(): HasMany
    {
        return $this->hasMany(FiscalDocument::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
