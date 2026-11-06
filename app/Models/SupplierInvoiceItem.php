<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceItem extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'supplier_invoice_id',
        'ingredient_id',
        'description',
        'classifier_code',
        'unit_id',
        'unit_name',
        'original_unit_name',
        'quantity',
        'original_quantity',
        'unit_price',
        'price',
        'vat_rate',
        'vat_amount',
        'total_price',
        'conversion_factor',
        'is_converted',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'original_quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'price' => 'decimal:2',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'total_price' => 'decimal:2',
        'conversion_factor' => 'decimal:4',
        'is_converted' => 'boolean',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
