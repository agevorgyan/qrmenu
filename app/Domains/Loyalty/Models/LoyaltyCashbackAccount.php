<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Customer;
use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyCashbackAccount extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_cashback_accounts';

    protected $fillable = [
        'vendor_id',
        'customer_id',
        'currency',
        'current_balance',
        'lifetime_earned',
        'lifetime_redeemed',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'lifetime_earned' => 'decimal:2',
            'lifetime_redeemed' => 'decimal:2',
            'last_activity_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyCashbackTransaction::class, 'account_id')->latest('created_at');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(LoyaltyCashbackLot::class, 'account_id')->where('is_expired', false)->orderBy('expires_at', 'asc');
    }
}
