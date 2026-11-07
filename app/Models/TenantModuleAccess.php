<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantModuleAccess extends Model
{
    use HasFactory;

    public const MODULE_WHOLESALE_POS = 'wholesale_pos';

    protected $table = 'tenant_module_access';

    protected $fillable = [
        'vendor_id',
        'module_key',
        'is_enabled',
        'enabled_at',
        'expires_at',
        'granted_by',
        'revoked_at',
        'revoked_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'enabled_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function grantedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TenantModuleAccessLog::class, 'tenant_module_access_id')->latest();
    }

    /**
     * Check if the module entitlement is currently active, valid, and not expired or revoked.
     */
    public function isValid(): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && Carbon::now()->greaterThanOrEqualTo($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Scope to filter valid, non-expired, non-revoked entitlements.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('is_enabled', true)
            ->whereNull('revoked_at')
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', Carbon::now());
            });
    }
}
