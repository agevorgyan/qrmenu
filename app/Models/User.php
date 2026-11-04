<?php

namespace App\Models;

use App\Security\Permission;
use App\Services\AuthorizationService;
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
        static::saving(function (User $user) {
            if ($user->email !== null) {
                $user->email = strtolower(trim($user->email));
            }
        });

        static::creating(function (User $user) {
            if (! array_key_exists('email_verified_at', $user->getAttributes())) {
                $user->email_verified_at = now();
            }
            if (! array_key_exists('is_active', $user->getAttributes())) {
                $user->is_active = true;
            }
        });

        static::created(function (User $user) {
            if ($user->vendor_id) {
                $membership = VendorMembership::firstOrCreate(
                    [
                        'vendor_id' => $user->vendor_id,
                        'user_id' => $user->id,
                    ],
                    [
                        'status' => $user->is_active ? 'active' : 'inactive',
                        'all_locations' => empty($user->location_id),
                        'roles_migrated' => true,
                        'joined_at' => now(),
                    ]
                );

                if (! empty($user->role)) {
                    $roleModel = Role::whereNull('vendor_id')->where('slug', $user->role)->first();
                    if ($roleModel) {
                        $membership->roles()->syncWithoutDetaching([$roleModel->id]);
                    }
                }

                if (! empty($user->location_id)) {
                    $membership->locations()->syncWithoutDetaching([$user->location_id]);
                }
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

    public function memberships(): HasMany
    {
        return $this->hasMany(VendorMembership::class);
    }

    public function activeMembership(?int $vendorId = null): ?VendorMembership
    {
        $vId = $vendorId ?? $this->vendor_id;
        if (! $vId) {
            return null;
        }

        if ($this->relationLoaded('memberships')) {
            return $this->memberships
                ->where('vendor_id', $vId)
                ->where('status', 'active')
                ->first();
        }

        return $this->memberships()
            ->where('vendor_id', $vId)
            ->where('status', 'active')
            ->first();
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

    public function isVendorOwner(?int $vendorId = null): bool
    {
        $vId = $vendorId ?? $this->vendor_id;
        if ($vId) {
            $membership = app(AuthorizationService::class)->resolveActiveMembership($this, $vId);
            if ($membership) {
                return $membership->hasRole('vendor_owner');
            }
            if ($this->relationLoaded('memberships') ? $this->memberships->where('vendor_id', $vId)->isNotEmpty() : $this->memberships()->where('vendor_id', $vId)->exists()) {
                return false;
            }
        }

        return $this->role === 'vendor_owner';
    }

    public function isManager(?int $vendorId = null): bool
    {
        $vId = $vendorId ?? $this->vendor_id;
        if ($vId) {
            $membership = app(AuthorizationService::class)->resolveActiveMembership($this, $vId);
            if ($membership) {
                return $membership->hasAnyRole(['vendor_owner', 'manager']);
            }
            if ($this->relationLoaded('memberships') ? $this->memberships->where('vendor_id', $vId)->isNotEmpty() : $this->memberships()->where('vendor_id', $vId)->exists()) {
                return false;
            }
        }

        return in_array($this->role, ['vendor_owner', 'manager'], true);
    }

    public function isStaff(?int $vendorId = null): bool
    {
        $vId = $vendorId ?? $this->vendor_id;
        if ($vId) {
            $membership = app(AuthorizationService::class)->resolveActiveMembership($this, $vId);
            if ($membership) {
                return $membership->hasAnyRole(['staff', 'waiter']);
            }
            if ($this->relationLoaded('memberships') ? $this->memberships->where('vendor_id', $vId)->isNotEmpty() : $this->memberships()->where('vendor_id', $vId)->exists()) {
                return false;
            }
        }

        return in_array($this->role, ['staff', 'waiter'], true);
    }

    public function isWaiter(?int $vendorId = null): bool
    {
        $vId = $vendorId ?? $this->vendor_id;
        if ($vId) {
            $membership = app(AuthorizationService::class)->resolveActiveMembership($this, $vId);
            if ($membership) {
                return $membership->hasAnyRole(['waiter', 'staff']);
            }
            if ($this->relationLoaded('memberships') ? $this->memberships->where('vendor_id', $vId)->isNotEmpty() : $this->memberships()->where('vendor_id', $vId)->exists()) {
                return false;
            }
        }

        return in_array($this->role, ['waiter', 'staff'], true);
    }

    public function isChef(?int $vendorId = null): bool
    {
        $vId = $vendorId ?? $this->vendor_id;
        if ($vId) {
            $membership = app(AuthorizationService::class)->resolveActiveMembership($this, $vId);
            if ($membership) {
                return $membership->hasRole('chef');
            }
            if ($this->relationLoaded('memberships') ? $this->memberships->where('vendor_id', $vId)->isNotEmpty() : $this->memberships()->where('vendor_id', $vId)->exists()) {
                return false;
            }
        }

        return $this->role === 'chef';
    }

    public function isCashier(?int $vendorId = null): bool
    {
        $vId = $vendorId ?? $this->vendor_id;
        if ($vId) {
            $membership = app(AuthorizationService::class)->resolveActiveMembership($this, $vId);
            if ($membership) {
                return $membership->hasRole('cashier');
            }
            if ($this->relationLoaded('memberships') ? $this->memberships->where('vendor_id', $vId)->isNotEmpty() : $this->memberships()->where('vendor_id', $vId)->exists()) {
                return false;
            }
        }

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
     * Get the default workspace landing URL after authentication based on assigned role.
     */
    public function getWorkspaceRedirectUrl(): string
    {
        if ($this->isSuperAdmin()) {
            return route('superadmin.dashboard');
        }

        return match ($this->role) {
            'waiter', 'staff' => route('pos.index', ['mode' => 'waiter']),
            'cashier' => route('pos.index', ['mode' => 'cashier']),
            'chef', 'kitchen' => route('pos.kds'),
            'manager' => route('pos.index', ['mode' => 'manager']),
            'vendor_owner' => route('admin.dashboard'),
            default => (! empty($this->pos_mode) && in_array($this->pos_mode, ['waiter', 'cashier', 'manager'], true))
                ? route('pos.index', ['mode' => $this->pos_mode])
                : route('admin.dashboard'),
        };
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
     * Get all active permissions for this user (delegated to AuthorizationService).
     */
    public function getPermissions(): array
    {
        return app(AuthorizationService::class)->getEffectivePermissions($this);
    }

    /**
     * Check if user has a specific permission (delegated to AuthorizationService).
     */
    public function hasPermission(string $permission, ?int $vendorId = null): bool
    {
        return app(AuthorizationService::class)->hasPermission($this, $permission, $vendorId);
    }

    /**
     * Check if user has any of the given permissions.
     */
    public function hasAnyPermission(array $permissions, ?int $vendorId = null): bool
    {
        return app(AuthorizationService::class)->hasAnyPermission($this, $permissions, $vendorId);
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
    public function hasAllPermissions(array $permissions, ?int $vendorId = null): bool
    {
        return app(AuthorizationService::class)->hasAllPermissions($this, $permissions, $vendorId);
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

        app(AuthorizationService::class)->invalidateCache($this->id, $this->vendor_id);

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

        app(AuthorizationService::class)->invalidateCache($this->id, $this->vendor_id);

        return $this;
    }

    /**
     * Check if the user is authorized to access a given Location model.
     */
    public function canAccessLocation(?Location $location, ?int $vendorId = null): bool
    {
        return app(AuthorizationService::class)->canAccessLocation($this, $location, $vendorId);
    }

    /**
     * Check if the user is authorized to access a given location ID.
     */
    public function canAccessLocationId(?int $locationId, ?int $vendorId = null): bool
    {
        return app(AuthorizationService::class)->canAccessLocationId($this, $locationId, $vendorId);
    }

    /**
     * Check if the user is authorized to access a given Order model (ReBAC).
     */
    public function canAccessOrder(Order $order): bool
    {
        return app(AuthorizationService::class)->canAccessOrder($this, $order);
    }

    /**
     * Check if the user is authorized to access a given RestaurantTable model (ReBAC).
     */
    public function canAccessTable(RestaurantTable $table): bool
    {
        return app(AuthorizationService::class)->canAccessTable($this, $table);
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
