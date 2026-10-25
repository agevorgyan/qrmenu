<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tip extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'tips';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'order_id',
        'table_number',
        'waiter_id',
        'payment_attempt_id',
        'amount',
        'currency',
        'tip_type',
        'percentage_applied',
        'payment_method',
        'payment_status',
        'allocation_policy',
        'allocation_status',
        'is_cash',
        'idempotency_key',
        'notes',
        'settled_at',
        'refunded_at',
        'refund_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage_applied' => 'decimal:2',
        'is_cash' => 'boolean',
        'settled_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tip $tip) {
            if (empty($tip->uuid)) {
                $tip->uuid = (string) Str::uuid();
            }
            if (empty($tip->currency)) {
                $tip->currency = 'AMD';
            }
            if (empty($tip->payment_status)) {
                $tip->payment_status = $tip->is_cash ? 'paid' : 'pending';
            }
            if ($tip->payment_status === 'paid' && empty($tip->settled_at)) {
                $tip->settled_at = now();
            }
        });

        static::saving(function (Tip $tip) {
            if ($tip->amount !== null && $tip->amount < 0) {
                throw new \InvalidArgumentException('Tip amount cannot be negative.');
            }

            if ($tip->order_id && $tip->vendor_id) {
                $order = Order::withoutGlobalScopes()->find($tip->order_id);
                if ($order && (int) $order->vendor_id !== (int) $tip->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor order attached to tip.');
                }
            }

            if ($tip->waiter_id && $tip->vendor_id) {
                $waiter = User::find($tip->waiter_id);
                if ($waiter && (int) $waiter->vendor_id !== (int) $tip->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor waiter attached to tip.');
                }
            }

            if ($tip->location_id && $tip->vendor_id) {
                $location = Location::withoutGlobalScopes()->find($tip->location_id);
                if ($location && (int) $location->vendor_id !== (int) $tip->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor location attached to tip.');
                }
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeRefunded(Builder $query): Builder
    {
        return $query->where('payment_status', 'refunded');
    }

    public function scopeForWaiter(Builder $query, int $waiterId): Builder
    {
        return $query->where('waiter_id', $waiterId);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function markAsPaid(?string $notes = null): void
    {
        $this->update([
            'payment_status' => 'paid',
            'settled_at' => now(),
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function markAsRefunded(?string $reason = null): void
    {
        $this->update([
            'payment_status' => 'refunded',
            'refunded_at' => now(),
            'refund_reason' => $reason,
        ]);
    }
}
