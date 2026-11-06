<?php

namespace App\Models;

use App\Models\Traits\HasContentTranslations;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasContentTranslations, HasFactory;

    protected $fillable = [
        'vendor_id',
        'code',
        'name',
        'name_translations',
        'type',
        'base_unit_id',
        'base_factor',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'base_factor' => 'decimal:6',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to return system units or units owned by current tenant.
     */
    public function scopeForCurrentVendor(Builder $query): Builder
    {
        $tenantId = app(TenantContext::class)->getTenantId();

        return $query->where(function (Builder $q) use ($tenantId) {
            $q->whereNull('vendor_id');
            if ($tenantId !== null) {
                $q->orWhere('vendor_id', $tenantId);
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function derivedUnits(): HasMany
    {
        return $this->hasMany(Unit::class, 'base_unit_id');
    }

    public function conversionsFrom(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    public function conversionsTo(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'to_unit_id');
    }
}
