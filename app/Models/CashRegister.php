<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class CashRegister extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'cash_registers';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'name',
        'code',
        'pos_device_id',
        'account_id',
        'status', // active, inactive, maintenance
        'is_active',
        'current_balance',
        'assigned_user_id',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(PosShift::class, 'cash_register_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(PosCashMovement::class, 'cash_register_id');
    }

    public function activeShift(): HasOne
    {
        return $this->hasOne(PosShift::class, 'cash_register_id')->where('status', 'open')->latestOfMany();
    }

    public function hasActiveShift(): bool
    {
        return $this->shifts()->where('status', 'open')->exists();
    }

    public function hasOpenShift(): bool
    {
        return $this->hasActiveShift();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    public function setIsActiveAttribute(mixed $value): void
    {
        $this->attributes['status'] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'active' : 'inactive';
    }

    public function getCurrencyAttribute(): string
    {
        return $this->location?->currency ?? $this->vendor?->currency ?? 'AMD';
    }

    public function getOpeningFloatAttribute(): float
    {
        return (float) ($this->activeShift?->opening_float ?? 0.00);
    }
}
