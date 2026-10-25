<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SplitBill extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'split_bills';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'order_id',
        'split_token',
        'status',
        'split_type',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'currency',
        'total_participants',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'total_participants' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SplitBill $bill) {
            if (empty($bill->uuid)) {
                $bill->uuid = (string) Str::uuid();
            }
            if (empty($bill->split_token)) {
                $bill->split_token = static::generateSplitToken();
            }
            if ($bill->remaining_amount === null) {
                $bill->remaining_amount = max(0, (float) $bill->total_amount - (float) ($bill->paid_amount ?? 0));
            }
        });

        static::saving(function (SplitBill $bill) {
            if ($bill->total_amount !== null && $bill->total_amount < 0) {
                throw new \InvalidArgumentException('Split bill total cannot be negative.');
            }
            if ($bill->paid_amount !== null && $bill->paid_amount < 0) {
                throw new \InvalidArgumentException('Split bill paid amount cannot be negative.');
            }
            if ($bill->order_id && $bill->vendor_id) {
                $order = Order::withoutGlobalScopes()->find($bill->order_id);
                if ($order && (int) $order->vendor_id !== (int) $bill->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor order attached to split bill.');
                }
            }
        });
    }

    public static function generateSplitToken(): string
    {
        return 'spt_'.bin2hex(random_bytes(20));
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SplitBillPayment::class);
    }

    public function paidPayments(): HasMany
    {
        return $this->hasMany(SplitBillPayment::class)->where('status', 'paid');
    }

    public function pendingPayments(): HasMany
    {
        return $this->hasMany(SplitBillPayment::class)->where('status', 'pending');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function isFullyPaid(): bool
    {
        return (float) $this->remaining_amount <= 0.001;
    }
}
