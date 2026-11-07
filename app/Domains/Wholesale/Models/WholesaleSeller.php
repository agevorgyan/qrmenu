<?php

namespace App\Domains\Wholesale\Models;

use App\Domains\Wholesale\Enums\SellerTaxRegime;
use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class WholesaleSeller extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'wholesale_sellers';

    protected $fillable = [
        'vendor_id',
        'name',
        'legal_name',
        'tin',
        'vat_number',
        'tax_regime',
        'address',
        'default_supply_location',
        'default_bank_account_id',
        'pek_username',
        'phone',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'tax_regime' => SellerTaxRegime::class,
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function taxProfiles(): HasMany
    {
        return $this->hasMany(WholesaleSellerTaxProfile::class, 'seller_id')->orderBy('effective_from', 'desc');
    }

    public function activeTaxProfile(): HasOne
    {
        $today = Carbon::today()->toDateString();

        return $this->hasOne(WholesaleSellerTaxProfile::class, 'seller_id')
            ->where('effective_from', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today);
            })
            ->latestOfMany('effective_from');
    }

    public function taxProfile(): HasOne
    {
        return $this->activeTaxProfile();
    }

    public function locations(): HasMany
    {
        return $this->hasMany(WholesaleSellerLocation::class, 'seller_id');
    }

    public function defaultLocation(): HasOne
    {
        return $this->hasOne(WholesaleSellerLocation::class, 'seller_id')
            ->where('is_default', true);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(WholesaleSellerBankAccount::class, 'seller_id');
    }

    public function defaultBankAccount(): BelongsTo
    {
        return $this->belongsTo(WholesaleSellerBankAccount::class, 'default_bank_account_id');
    }

    /**
     * Check if this seller is registered as a VAT payer at a given date.
     */
    public function isVatPayer(?Carbon $date = null): bool
    {
        $checkDate = ($date ?: Carbon::today())->toDateString();
        $profile = $this->taxProfiles()
            ->where('effective_from', '<=', $checkDate)
            ->where(function ($q) use ($checkDate) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $checkDate);
            })
            ->latest('effective_from')
            ->first();

        if ($profile) {
            return (bool) $profile->is_vat_payer;
        }

        return $this->tax_regime === SellerTaxRegime::VAT_PAYER;
    }

    /**
     * Format full legal name and TIN for selection boxes.
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} (ՀՎՀՀ: {$this->tin})";
    }
}
