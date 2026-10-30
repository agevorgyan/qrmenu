<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosCashMovement extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_cash_movements';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'pos_shift_id',
        'pos_device_id',
        'user_id',
        'type',
        'amount',
        'reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PosCashMovement $movement): void {
            if (empty($movement->uuid)) {
                $movement->uuid = (string) Str::uuid();
            }
        });

        static::saved(function (PosCashMovement $movement): void {
            $movement->posShift?->recalculateExpectedCash();
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function posShift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'pos_shift_id');
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class, 'pos_device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
