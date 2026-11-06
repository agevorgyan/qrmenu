<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierInvoice extends Model
{
    use BelongsToVendor, HasFactory;

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PARTIALLY_PAID = 'partially_paid';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_OVERDUE = 'overdue';

    protected $fillable = [
        'vendor_id',
        'supplier_id',
        'purchase_order_id',
        'warehouse_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'remaining_balance',
        'payment_status',
        'received_by',
        'notes',
        'document_url',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_balance' => 'decimal:2',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class, 'supplier_invoice_id')->orderByDesc('payment_date');
    }

    /**
     * Recompute payment status and remaining balance after payments.
     */
    public function recalculatePaymentState(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $total = (float) $this->total_amount;
        $remaining = max(0.0, round($total - $paid, 2));

        $this->paid_amount = $paid;
        $this->remaining_balance = $remaining;

        if ($remaining <= 0.0) {
            $this->payment_status = self::PAYMENT_PAID;
        } elseif ($paid > 0.0) {
            $this->payment_status = self::PAYMENT_PARTIALLY_PAID;
        } elseif ($this->due_date && $this->due_date->isPast()) {
            $this->payment_status = self::PAYMENT_OVERDUE;
        } else {
            $this->payment_status = self::PAYMENT_UNPAID;
        }

        $this->save();
        $this->supplier->recalculateBalance();
    }
}
