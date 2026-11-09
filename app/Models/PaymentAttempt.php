<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentAttempt extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'uuid',
        'vendor_id',
        'order_id',
        'subscription_id',
        'gateway',
        'merchant_reference',
        'provider_transaction_id',
        'amount',
        'currency',
        'status',
        'request_payload',
        'response_payload',
        'idempotency_key',
        'verified_at',
        'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => PaymentStatus::class,
        'request_payload' => 'array',
        'response_payload' => 'array',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (PaymentAttempt $attempt): void {
            if (empty($attempt->uuid)) {
                $attempt->uuid = (string) Str::uuid();
            }
            if (empty($attempt->status)) {
                $attempt->status = PaymentStatus::Created;
            }
            if (empty($attempt->expires_at)) {
                $attempt->expires_at = now()->addMinutes(30);
            }
        });

        static::saving(function (PaymentAttempt $attempt): void {
            if ($attempt->amount !== null && $attempt->amount < 0) {
                throw new \InvalidArgumentException('Payment attempt amount cannot be negative.');
            }

            if ($attempt->order_id && $attempt->vendor_id) {
                $order = Order::withoutGlobalScopes()->find($attempt->order_id);
                if ($order && (int) $order->vendor_id !== (int) $attempt->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor order attached to payment attempt.');
                }
            }

            if ($attempt->subscription_id && $attempt->vendor_id) {
                $sub = SubscriptionPayment::withoutGlobalScopes()->find($attempt->subscription_id);
                if ($sub && (int) $sub->vendor_id !== (int) $attempt->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor subscription attached to payment attempt.');
                }
            }
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

    public function subscriptionPayment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPayment::class, 'subscription_id');
    }

    public function isPaid(): bool
    {
        return $this->status === PaymentStatus::Paid;
    }

    public function isSubscriptionPayment(): bool
    {
        return ! empty($this->subscription_id);
    }

    public function isExpired(): bool
    {
        if ($this->status === PaymentStatus::Expired) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function canTransitionTo(PaymentStatus $target): bool
    {
        return $this->status->canTransitionTo($target);
    }
}
