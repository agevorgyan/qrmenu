<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Order;
use App\Models\Traits\BelongsToVendor;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyStampTransaction extends Model
{
    use BelongsToVendor, HasFactory;

    public $timestamps = false;

    protected $table = 'loyalty_stamp_transactions';

    protected $fillable = [
        'vendor_id',
        'card_id',
        'order_id',
        'staff_id',
        'type',
        'stamps',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'stamps' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LoyaltyStampTransaction $tx) {
            if (empty($tx->created_at)) {
                $tx->created_at = now();
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(LoyaltyStampCard::class, 'card_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
