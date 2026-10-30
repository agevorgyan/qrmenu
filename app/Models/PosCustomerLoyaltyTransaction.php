<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosCustomerLoyaltyTransaction extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_customer_loyalty_transactions';

    protected $fillable = [
        'vendor_id',
        'customer_id',
        'order_id',
        'type', // 'earned', 'redeemed', 'adjusted', 'expired'
        'points',
        'balance_after',
        'notes',
    ];

    protected $casts = [
        'points' => 'integer',
        'balance_after' => 'integer',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
