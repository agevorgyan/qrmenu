<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosPayment extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_payments';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'order_id',
        'pos_shift_id',
        'pos_device_id',
        'user_id',
        'payment_method',
        'amount',
        'cash_tendered',
        'change_due',
        'tip_amount',
        'currency',
        'status',
        'transaction_reference',
        'refunded_amount',
        'notes',
        'is_offline',
        'client_sync_id',
        'offline_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'cash_tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
            'tip_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'is_offline' => 'boolean',
            'offline_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PosPayment $payment): void {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
            if (empty($payment->status)) {
                $payment->status = 'completed';
            }
            if (empty($payment->currency)) {
                $payment->currency = 'AMD';
            }
        });

        static::saved(function (PosPayment $payment): void {
            $payment->posShift?->recalculateExpectedCash();
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
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
