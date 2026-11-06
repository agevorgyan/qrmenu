<?php

namespace App\Domains\Loyalty\Models;

use App\Domains\Loyalty\Enums\StampStatus;
use App\Models\Customer;
use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LoyaltyStampCard extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_stamp_cards';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'program_id',
        'customer_id',
        'stamps_collected',
        'stamps_required',
        'status',
        'completed_at',
        'redeemed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'stamps_collected' => 'integer',
            'stamps_required' => 'integer',
            'status' => StampStatus::class,
            'completed_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LoyaltyStampCard $card) {
            if (empty($card->uuid)) {
                $card->uuid = (string) Str::uuid();
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(LoyaltyStampProgram::class, 'program_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyStampTransaction::class, 'card_id')->latest('created_at');
    }
}
