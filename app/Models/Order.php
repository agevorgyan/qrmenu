<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'location_id',
        'customer_id',
        'order_number',
        'tracking_token',
        'table_number',
        'delivery_address',
        'type',
        'payment_method',
        'payment_status',
        'payment_transaction_id',
        'subtotal',
        'service_fee',
        'delivery_fee',
        'tip_amount',
        'birthday_discount_amount',
        'total_amount',
        'status',
        'waiter_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'customer_birthdate',
        'marketing_opt_in',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'birthday_discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'marketing_opt_in' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->tracking_token)) {
                $order->tracking_token = static::generateTrackingToken();
            }
        });

        static::saving(function (Order $order) {
            if ($order->total_amount !== null && $order->total_amount < 0) {
                throw new \InvalidArgumentException('Order total cannot be negative.');
            }

            if ($order->location_id && $order->vendor_id) {
                $location = Location::withoutGlobalScopes()->find($order->location_id);
                if ($location && (int) $location->vendor_id !== (int) $order->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor location assigned to order.');
                }
            }

            if ($order->customer_id && $order->vendor_id) {
                $customer = Customer::withoutGlobalScopes()->find($order->customer_id);
                if ($customer && (int) $customer->vendor_id !== (int) $order->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor customer attached to order.');
                }
            }

            if ($order->waiter_id && $order->vendor_id) {
                $waiter = User::find($order->waiter_id);
                if ($waiter && (int) $waiter->vendor_id !== (int) $order->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor waiter attached to order.');
                }
            }
        });
    }

    /**
     * Generate an unguessable cryptographic tracking token for public access.
     */
    public static function generateTrackingToken(): string
    {
        return 'trk_'.bin2hex(random_bytes(24));
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function paymentAttempts()
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function latestPaymentAttempt()
    {
        return $this->hasOne(PaymentAttempt::class)->latestOfMany();
    }

    public function waiter()
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function tip()
    {
        return $this->hasOne(Tip::class);
    }

    public function serviceRating()
    {
        return $this->hasOne(ServiceRating::class);
    }

    public function splitBills()
    {
        return $this->hasMany(SplitBill::class);
    }

    public function activeSplitBill()
    {
        return $this->hasOne(SplitBill::class)->where('status', 'active')->latestOfMany();
    }
}
