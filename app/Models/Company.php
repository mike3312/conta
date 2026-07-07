<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Account;
class Company extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasUuid;

    protected $fillable = [
        'tenant_id',
        'name',
        'legal_name',
        'tax_id',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'currency',
        'timezone',
        'logo',
        'status',
    ];

    protected $casts = [
        'status' => CompanyStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot([
                'is_owner',
                'is_active',
                'joined_at',
            ])
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CompanyStatus::ACTIVE);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', CompanyStatus::INACTIVE);
    }
public function owners(): BelongsToMany
{
    return $this->users()
        ->wherePivot('is_owner', true);
}

public function tenant(): BelongsTo
{
    return $this->belongsTo(Tenant::class);
}

public function accounts()
{
    return $this->hasMany(Account::class);
}

}