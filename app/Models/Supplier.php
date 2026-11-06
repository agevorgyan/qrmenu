<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'name',
        'code',
        'tax_id',
        'contact_person',
        'phone',
        'email',
        'address',
        'bank_name',
        'bank_account',
        'payment_terms',
        'delivery_schedule',
        'minimum_order_amount',
        'rating',
        'notes',
        'current_balance',
        'is_active',
    ];

    protected $casts = [
        'delivery_schedule' => 'array',
        'minimum_order_amount' => 'decimal:2',
        'rating' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function ingredients(): BelongsToMany
    {
        return $this->belongsToMany(Ingredient::class, 'supplier_ingredients')
            ->withPivot([
                'supplier_sku',
                'unit_id',
                'price',
                'is_primary_supplier',
                'lead_time_days',
                'minimum_order_quantity',
                'package_size',
            ])
            ->withTimestamps();
    }

    public function supplierIngredients(): HasMany
    {
        return $this->hasMany(SupplierIngredient::class, 'supplier_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id')->orderByDesc('order_date');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class, 'supplier_id')->orderByDesc('invoice_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class, 'supplier_id')->orderByDesc('payment_date');
    }

    /**
     * Recalculate and update the supplier's outstanding balance ledger.
     */
    public function recalculateBalance(): float
    {
        $totalInvoiced = (float) $this->invoices()->sum('total_amount');
        $totalPaid = (float) $this->payments()->sum('amount');
        $balance = max(0.0, round($totalInvoiced - $totalPaid, 2));

        $this->current_balance = $balance;
        $this->save();

        return $balance;
    }
}
