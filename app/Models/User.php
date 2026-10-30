<?php

namespace App\Models;

use App\Security\Permission;
use App\Services\EntitlementService;
use App\Services\SecurityAuditService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'display_name',
        'short_description',
        'employee_id',
        'avatar_url',
        'is_active',
        'sort_order',
        'assigned_table_ids',
        'email',
        'email_verified_at',
        'password',
        'role',
        'custom_permissions',
        'vendor_id',
        'location_id',
        'phone',
        'two_factor_enabled',
        'two_factor_type',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_email_code',
        'two_factor_email_expires_at',
        'locale',
        'pin_code',
        'pos_mode',
    ];

    protected $hidden = [
        'password',
        'pin_code',
        'remember_token',
        'two_factor_secret',
        'two_factor_email_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'custom_permissions' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'assigned_table_ids' => 'array',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_email_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (! array_key_exists('email_verified_at', $user->getAttributes())) {
                $user->email_verified_at = now();
            }
        });

        static::saving(function (User $user) {
            if ($user->location_id && $user->vendor_id) {
                $location = Location::withoutGlobalScopes()->find($user->location_id);
                if ($location && (int) $location->vendor_id !== (int) $user->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor location assigned to user.');
                }
            }
        });

        static::updated(function (User $user) {
            if ($user->wasChanged('role')) {
                app(SecurityAuditService::class)->logRoleChange(
                    user: $user,
                    oldRole: (string) ($user->getOriginal('role') ?? 'none'),
                    newRole: (string) $user->role,
                    actor: auth()->user(),
                    vendor: $user->vendor
                );
            }

            if ($user->wasChanged('password')) {
                app(SecurityAuditService::class)->logPasswordChange(
                    user: $user,
                    actor: auth()->user(),
                    vendor: $user->vendor
                );
            }

            if ($user->wasChanged('two_factor_enabled')) {
                app(SecurityAuditService::class)->logTwoFactorChange(
                    user: $user,
                    actionType: $user->two_factor_enabled ? 'enabled' : 'disabled',
                    type: $user->two_factor_type ?? 'authenticator',
                    vendor: $user->vendor
                );
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function assignedTables(): BelongsToMany
    {
        return $this->belongsToMany(RestaurantTable::class, 'restaurant_table_user', 'user_id', 'restaurant_table_id')
            ->withTimestamps();
    }

    public function tips(): HasMany
    {
        return $this->hasMany(Tip::class, 'waiter_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(ServiceRating::class, 'waiter_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'waiter_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isVendorOwner(): bool
    {
        return $this->role === 'vendor_owner';
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['vendor_owner', 'manager']);
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['staff', 'waiter']);
    }

    public function isWaiter(): bool
    {
        return in_array($this->role, ['waiter', 'staff']);
    }

    public function isChef(): bool
    {
        return $this->role === 'chef';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function hasPin(): bool
    {
        return ! empty($this->pin_code);
    }

    public function verifyPin(string $pin): bool
    {
        if (empty($this->pin_code)) {
            return false;
        }

        return Hash::check($pin, $this->pin_code);
    }

    public function setPin(string $pin): void
    {
        $this->update([
            'pin_code' => Hash::make($pin),
        ]);
    }

    /**
     * Get the customer-facing display name.
     */
    public function getPublicDisplayNameAttribute(): string
    {
        return ! empty($this->display_name) ? $this->display_name : $this->name;
    }

    /**
     * Scope query to active waiters for a vendor.
     */
    public function scopeActiveWaiters(Builder $query): Builder
    {
        return $query->whereIn('role', ['waiter', 'staff', 'manager', 'cashier'])
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc');
    }

    /**
     * Get all active permissions for this user (role defaults + custom overrides).
     */
    public function getPermissions(): array
    {
        $roleDefaults = Permission::forRole($this->role ?? '');

        $custom = $this->custom_permissions ?? [];
        $granted = $custom['granted'] ?? [];
        $denied = $custom['denied'] ?? [];

        $effective = array_unique(array_merge($roleDefaults, $granted));

        if (! empty($denied)) {
            $effective = array_values(array_diff($effective, $denied));
        }

        return $effective;
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        // Explicit platform permissions for superadmin
        if ($this->isSuperAdmin()) {
            $perms = $this->getPermissions();

            return in_array($permission, $perms, true);
        }

        return in_array($permission, $this->getPermissions(), true);
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user's tenant is entitled to a feature, and optionally whether user has the required permission.
     */
    public function canAccessFeature(string $feature, ?string $requiredUserPermission = null): bool
    {
        return app(EntitlementService::class)->canUserAccessFeature($this, $feature, $requiredUserPermission);
    }

    /**
     * Check if user has all of the given permissions.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (! $this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Explicitly grant a custom permission override.
     */
    public function givePermission(string $permission): self
    {
        $custom = $this->custom_permissions ?? ['granted' => [], 'denied' => []];
        $granted = $custom['granted'] ?? [];
        $denied = $custom['denied'] ?? [];

        if (! in_array($permission, $granted, true)) {
            $granted[] = $permission;
        }

        $denied = array_values(array_diff($denied, [$permission]));

        $this->custom_permissions = [
            'granted' => $granted,
            'denied' => $denied,
        ];

        return $this;
    }

    /**
     * Explicitly revoke/deny a custom permission override.
     */
    public function revokePermission(string $permission): self
    {
        $custom = $this->custom_permissions ?? ['granted' => [], 'denied' => []];
        $granted = $custom['granted'] ?? [];
        $denied = $custom['denied'] ?? [];

        if (! in_array($permission, $denied, true)) {
            $denied[] = $permission;
        }

        $granted = array_values(array_diff($granted, [$permission]));

        $this->custom_permissions = [
            'granted' => $granted,
            'denied' => $denied,
        ];

        return $this;
    }

    /**
     * Check if the user is authorized to access a given Location model.
     */
    public function canAccessLocation(?Location $location): bool
    {
        if (! $location) {
            return true;
        }

        if ((int) $this->vendor_id !== (int) $location->vendor_id) {
            return false;
        }

        if ($this->location_id !== null) {
            return (int) $this->location_id === (int) $location->id;
        }

        return true;
    }

    /**
     * Check if the user is authorized to access a given location ID.
     */
    public function canAccessLocationId(?int $locationId): bool
    {
        if ($locationId === null) {
            return true;
        }

        if ($this->location_id !== null) {
            return (int) $this->location_id === (int) $locationId;
        }

        return true;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return (bool) $this->two_factor_enabled;
    }

    public function maskedEmail(): string
    {
        $parts = explode('@', $this->email);
        $name = $parts[0] ?? '';
        $domain = $parts[1] ?? '';

        if (strlen($name) <= 2) {
            $maskedName = substr($name, 0, 1).'*';
        } else {
            $maskedName = substr($name, 0, 2).str_repeat('*', max(strlen($name) - 3, 2)).substr($name, -1);
        }

        return $maskedName.'@'.$domain;
    }

    public function serviceRatings(): HasMany
    {
        return $this->ratings();
    }
}
