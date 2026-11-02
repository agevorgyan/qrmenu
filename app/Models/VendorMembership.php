<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'user_id',
        'status',
        'all_locations',
        'roles_migrated',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'all_locations' => 'boolean',
            'roles_migrated' => 'boolean',
            'joined_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'membership_roles',
            'membership_id',
            'role_id'
        )->withTimestamps();
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(
            Location::class,
            'membership_locations',
            'membership_id',
            'location_id'
        )->withTimestamps();
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(MembershipPermission::class, 'membership_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function canAccessLocationId(?int $locationId): bool
    {
        if ($locationId === null || $this->all_locations) {
            return true;
        }

        return $this->locations->contains('id', $locationId);
    }

    public function hasRole(string $slug): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->where('is_active', true)->contains('slug', $slug);
        }

        return $this->roles()->where('is_active', true)->where('slug', $slug)->exists();
    }

    public function hasAnyRole(array $slugs): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->relationLoaded('roles')) {
            return $this->roles->where('is_active', true)->whereIn('slug', $slugs)->isNotEmpty();
        }

        return $this->roles()->where('is_active', true)->whereIn('slug', $slugs)->exists();
    }
}
