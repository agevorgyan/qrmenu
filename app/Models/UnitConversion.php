<?php

namespace App\Models;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitConversion extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'ingredient_id',
        'from_unit_id',
        'to_unit_id',
        'factor',
    ];

    protected $casts = [
        'factor' => 'decimal:6',
    ];

    /**
     * Scope to return system-wide conversions or conversions owned by current vendor.
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

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }
}
