<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyPointLot extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'loyalty_point_lots';

    protected $fillable = [
        'vendor_id',
        'account_id',
        'transaction_id',
        'initial_points',
        'remaining_points',
        'expires_at',
        'is_expired',
    ];

    protected function casts(): array
    {
        return [
            'initial_points' => 'integer',
            'remaining_points' => 'integer',
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
        return $this->belongsTo(LoyaltyPointsAccount::class, 'account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LoyaltyLedgerTransaction::class, 'transaction_id');
    }
}
