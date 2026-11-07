<?php

namespace App\Domains\Wholesale\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WholesaleOrder extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'wholesale_orders';

    protected $fillable = [
        'vendor_id',
        'seller_id',
        'customer_id',
        'order_number',
        'order_type',
        'status',
        'payment_status',
        'order_date',
        'expected_delivery_date',
        'delivery_address_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'delivery_fee',
        'total_amount',
        'paid_amount',
        'notes',
        'created_by_user_id',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'confirmed_at' => 'datetime',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'delivery_fee' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'paid_amount' => 'decimal:4',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(WholesaleSeller::class, 'seller_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WholesaleCustomer::class, 'customer_id');
    }

    public function deliveryAddress(): BelongsTo
    {
        return $this->belongsTo(WholesaleCustomerAddress::class, 'delivery_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WholesaleOrderItem::class, 'order_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(WholesaleSale::class, 'order_id');
    }

    /**
     * Recalculate and update order totals based on current items.
     */
    public function recalculateTotals(): void
    {
        $items = $this->items()->get();

        $subtotal = $items->sum('subtotal');
        $discountTotal = $items->sum('discount_amount');
        $taxTotal = $items->sum('vat_amount');
        $deliveryFee = (float) $this->delivery_fee;
        $totalAmount = round($subtotal + $taxTotal + $deliveryFee, 2);

        $this->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Check if all ordered items have been fully delivered.
     */
    public function isFullyDelivered(): bool
    {
        $items = $this->items()->get();
        if ($items->isEmpty()) {
            return false;
        }

        foreach ($items as $item) {
            if ((float) $item->quantity_delivered < (float) $item->quantity_ordered) {
                return false;
            }
        }

        return true;
    }
}

