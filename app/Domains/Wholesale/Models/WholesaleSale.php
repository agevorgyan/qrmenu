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

class WholesaleSale extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'wholesale_sales';

    protected $fillable = [
        'vendor_id',
        'order_id',
        'seller_id',
        'customer_id',
        'invoice_number',
        'sale_date',
        'status',
        'payment_method',
        'subtotal',
        'discount_total',
        'tax_total',
        'delivery_fee',
        'total_amount',
        'paid_amount',
        'due_date',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'due_date' => 'date',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(WholesaleOrder::class, 'order_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(WholesaleSeller::class, 'seller_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WholesaleCustomer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WholesaleSaleItem::class, 'sale_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Recalculate totals from items.
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
}
