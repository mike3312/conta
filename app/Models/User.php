<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
   use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function role()
{
    return $this->belongsTo(Role::class);
}
/*
|--------------------------------------------------------------------------
| Relationships
|--------------------------------------------------------------------------
*/

public function companies(): BelongsToMany
{
    return $this->belongsToMany(Company::class)
        ->withPivot([
            'is_owner',
            'is_active',
            'joined_at',
        ])
        ->withTimestamps();
}
public function ownedCompanies(): BelongsToMany
{
    return $this->companies()
        ->wherePivot('is_owner', true);
}

public function tenant(): BelongsTo
{
    return $this->belongsTo(Tenant::class);
}

}
