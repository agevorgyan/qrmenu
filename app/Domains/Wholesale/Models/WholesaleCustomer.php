<?php

namespace App\Domains\Wholesale\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class WholesaleCustomer extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'wholesale_customers';

    protected $fillable = [
        'vendor_id',
        'name',
        'legal_name',
        'tin',
        'tax_regime',
        'credit_limit',
        'payment_term_days',
        'is_credit_allowed',
        'current_balance',
        'phone',
        'email',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:4',
            'current_balance' => 'decimal:4',
            'payment_term_days' => 'integer',
            'is_credit_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(WholesaleCustomerAddress::class, 'customer_id');
    }

    public function defaultAddress(): HasOne
    {
        return $this->hasOne(WholesaleCustomerAddress::class, 'customer_id')->where('is_default', true);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(WholesaleCustomerContact::class, 'customer_id');
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(WholesaleCustomerContact::class, 'customer_id')->where('is_primary', true);
    }

    public function pricingAgreements(): HasMany
    {
        return $this->hasMany(WholesaleCustomerPricing::class, 'customer_id');
    }

    /**
     * Determine if customer has sufficient available credit for a new invoice/sale.
     */
    public function canAffordCredit(float $amount): bool
    {
        if (! $this->is_credit_allowed) {
            return false;
        }

        // 0 credit limit means unlimited if credit allowed, otherwise cap by limit
        if ((float) $this->credit_limit <= 0) {
            return true;
        }

        return ((float) $this->current_balance + $amount) <= (float) $this->credit_limit;
    }
}

