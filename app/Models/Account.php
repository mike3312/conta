<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Enums\AccountNature;
use App\Enums\AccountType;

class Account extends Model
{
    protected $fillable = [
        'company_id',
        'parent_id',
        'code',
        'name',
        'account_type',
        'nature',
        'allows_entries',
        'level',
        'is_active',
    ];

protected $casts = [
    'account_type' => AccountType::class,
    'nature' => AccountNature::class,
    'allows_entries' => 'boolean',
    'is_active' => 'boolean',
];

public function getIndentedNameAttribute(): string
{
    return str_repeat('— ', max(0, $this->level - 1)) . $this->code . ' - ' . $this->name;
}

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id')
            ->orderBy('code');
    }

    public function isHeader(): bool
{
    return ! $this->allows_entries;
}

public function isMovement(): bool
{
    return $this->allows_entries;
}

public function canReceiveChildren(): bool
{
    return $this->isHeader() && $this->is_active;
}

public function canReceiveEntries(): bool
{
    return $this->isMovement() && $this->is_active;
}

public function hasChildren(): bool
{
    return $this->children()->exists();
}
}