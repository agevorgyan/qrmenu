<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyReferral extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_referrals';

    protected $fillable = [
        'vendor_id',
        'referrer_customer_id',
        'referred_customer_id',
        'referral_code',
        'status',
        'qualifying_order_id',
        'referrer_points_awarded',
        'referred_points_awarded',
        'qualified_at',
    ];

    protected function casts(): array
    {
        return [
            'referrer_points_awarded' => 'integer',
            'referred_points_awarded' => 'integer',
            'qualified_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    public function qualifyingOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'qualifying_order_id');
    }
}
