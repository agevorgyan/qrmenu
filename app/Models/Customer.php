<?php

namespace App\Models;

use App\Domains\Loyalty\Models\LoyaltyCashbackAccount;
use App\Domains\Loyalty\Models\LoyaltyPointsAccount;
use App\Domains\Loyalty\Models\LoyaltyReferral;
use App\Domains\Loyalty\Models\LoyaltyRewardRedemption;
use App\Domains\Loyalty\Models\LoyaltyStampCard;
use App\Domains\Loyalty\Models\LoyaltyTierHistory;
use App\Domains\Loyalty\Models\WalletPass;
use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'location_id',
        'name',
        'phone',
        'email',
        'birthdate',
        'address',
        'notes',
        'marketing_opt_in',
        'total_orders_count',
        'total_spent',
        'loyalty_points',
        'loyalty_tier',
        'last_order_at',
    ];

    protected $casts = [
        'marketing_opt_in' => 'boolean',
        'total_spent' => 'decimal:2',
        'loyalty_points' => 'integer',
        'last_order_at' => 'datetime',
        'birthdate' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            if ($customer->location_id && $customer->vendor_id) {
                $location = Location::withoutGlobalScopes()->find($customer->location_id);
                if ($location && (int) $location->vendor_id !== (int) $customer->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor location assigned to customer.');
                }
            }
        });
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class)->latest('reservation_date');
    }

    public function eventInquiries()
    {
        return $this->hasMany(EventInquiry::class)->latest('event_date');
    }

    public function recalculateStats()
    {
        $this->total_orders_count = $this->orders()->count();
        $this->total_spent = $this->orders()->sum('total_amount');
        $this->last_order_at = $this->orders()->max('created_at');
        $this->save();
    }

    public function loyaltyTransactions()
    {
        return $this->hasMany(PosCustomerLoyaltyTransaction::class)->latest();
    }

    public function pointsAccount()
    {
        return $this->hasOne(LoyaltyPointsAccount::class);
    }

    public function cashbackAccount()
    {
        return $this->hasOne(LoyaltyCashbackAccount::class);
    }

    public function stampCards()
    {
        return $this->hasMany(LoyaltyStampCard::class)->latest();
    }

    public function walletPasses()
    {
        return $this->hasMany(WalletPass::class)->latest();
    }

    public function rewardRedemptions()
    {
        return $this->hasMany(LoyaltyRewardRedemption::class)->latest();
    }

    public function tierHistories()
    {
        return $this->hasMany(LoyaltyTierHistory::class)->latest();
    }

    public function referrals()
    {
        return $this->hasMany(LoyaltyReferral::class, 'referrer_customer_id')->latest();
    }
}
