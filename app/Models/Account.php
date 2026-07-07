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
}