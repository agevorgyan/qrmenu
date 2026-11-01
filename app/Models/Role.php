<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'name',
        'slug',
        'description',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'role_permissions',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(
            VendorMembership::class,
            'membership_roles',
            'role_id',
            'membership_id'
        )->withTimestamps();
    }

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('is_system', true);
    }

    public function scopeForVendor(Builder $query, ?int $vendorId): Builder
    {
        if ($vendorId === null) {
            return $query->whereNull('vendor_id');
        }

        return $query->where(function (Builder $q) use ($vendorId) {
            $q->whereNull('vendor_id')
                ->orWhere('vendor_id', $vendorId);
        });
    }

    public function scopeTenantCustom(Builder $query, int $vendorId): Builder
    {
        return $query->where('vendor_id', $vendorId)->where('is_system', false);
    }
}
