<?php

namespace App\Models;

use App\Enums\VatDeclarationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VatDeclaration extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'status' => VatDeclarationStatus::class,
        'calculated_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'ready_at' => 'datetime',
        'filed_at' => 'datetime',
        'amount_paid' => 'decimal:2',
    ];

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VatDeclarationDocument::class);
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function readyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ready_by');
    }

    public function filedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by');
    }
}
