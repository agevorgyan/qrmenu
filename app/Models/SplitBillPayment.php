<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SplitBillPayment extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'split_bill_payments';

    protected $fillable = [
        'uuid',
        'split_bill_id',
        'vendor_id',
        'order_id',
        'payment_attempt_id',
        'participant_name',
        'amount',
        'currency',
        'status',
        'payment_method',
        'payment_reference',
        'items_breakdown',
        'idempotency_key',
        'paid_at',
        'expires_at',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'items_breakdown' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SplitBillPayment $payment) {
            if (empty($payment->uuid)) {
                $payment->uuid = (string) Str::uuid();
            }
            if (empty($payment->status)) {
                $payment->status = 'pending';
            }
        });

        static::saving(function (SplitBillPayment $payment) {
            if ($payment->amount !== null && $payment->amount <= 0) {
                throw new \InvalidArgumentException('Split payment amount must be greater than zero.');
            }

            if ($payment->split_bill_id && $payment->vendor_id) {
                $bill = $payment->splitBill ?: SplitBill::withoutGlobalScopes()->find($payment->split_bill_id);
                if ($bill && (int) $bill->vendor_id !== (int) $payment->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor split bill attached to payment.');
                }
            }

            if ($payment->order_id && $payment->vendor_id) {
                $order = $payment->order ?: Order::withoutGlobalScopes()->find($payment->order_id);
                if ($order && (int) $order->vendor_id !== (int) $payment->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor order attached to split payment.');
                }
            }
        });
    }

    public function splitBill(): BelongsTo
    {
        return $this->belongsTo(SplitBill::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(PaymentAttempt::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}
