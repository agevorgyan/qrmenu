<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'subscription_id',
        'subscription_plan_id',
        'amount',
        'currency',
        'payment_method',
        'invoice_number',
        'period_start',
        'period_end',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (SubscriptionPayment $payment): void {
            if ($payment->amount !== null && $payment->amount < 0) {
                throw new \InvalidArgumentException('Subscription payment amount cannot be negative.');
            }
        });
    }

    public function getCurrencyAttribute(?string $value): string
    {
        $val = trim((string) ($value ?: '֏'));

        return strtoupper($val) === 'AMD' ? '֏' : $val;
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function paymentAttempts()
    {
        return $this->hasMany(PaymentAttempt::class, 'subscription_id');
    }

    public function latestPaymentAttempt()
    {
        return $this->hasOne(PaymentAttempt::class, 'subscription_id')->latestOfMany();
    }
}
