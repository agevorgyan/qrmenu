<?php

namespace App\Domains\Loyalty\Models;

use App\Domains\Loyalty\Enums\LoyaltyTransactionType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Traits\BelongsToVendor;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LoyaltyLedgerTransaction extends Model
{
    use BelongsToVendor, HasFactory;

    public $timestamps = false;

    protected $table = 'loyalty_ledger_transactions';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'account_id',
        'customer_id',
        'order_id',
        'staff_id',
        'type',
        'points',
        'balance_before',
        'balance_after',
        'idempotency_key',
        'reference_type',
        'reference_id',
        'notes',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => LoyaltyTransactionType::class,
            'points' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LoyaltyLedgerTransaction $tx) {
            if (empty($tx->uuid)) {
                $tx->uuid = (string) Str::uuid();
            }
            if (empty($tx->created_at)) {
                $tx->created_at = now();
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyPointsAccount::class, 'account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(LoyaltyPointLot::class, 'transaction_id');
    }
}
