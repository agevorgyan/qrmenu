<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyCashbackLot extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_cashback_lots';

    protected $fillable = [
        'vendor_id',
        'account_id',
        'transaction_id',
        'initial_amount',
        'remaining_amount',
        'expires_at',
        'is_expired',
    ];

    protected function casts(): array
    {
        return [
            'initial_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'is_expired' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCashbackAccount::class, 'account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCashbackTransaction::class, 'transaction_id');
    }
}
