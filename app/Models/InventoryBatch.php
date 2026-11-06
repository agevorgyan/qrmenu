<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryBatch extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_QUARANTINED = 'quarantined';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_DEPLETED = 'depleted';

    public const SOURCE_PURCHASE = 'purchase';

    public const SOURCE_PRODUCTION = 'production';

    public const SOURCE_OPENING = 'opening_balance';

    public const SOURCE_TRANSFER = 'transfer';

    protected $fillable = [
        'vendor_id',
        'batch_number',
        'ingredient_id',
        'warehouse_id',
        'initial_quantity',
        'remaining_quantity',
        'unit_id',
        'unit_cost',
        'production_date',
        'expiry_date',
        'status',
        'source_type',
        'purchase_order_id',
        'production_order_id',
        'supplier_id',
        'notes',
    ];

    protected $casts = [
        'initial_quantity' => 'decimal:4',
        'remaining_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'production_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast();
    }

    public function deplete(float $qty): void
    {
        $newRemaining = max(0.0, (float) $this->remaining_quantity - $qty);
        $this->remaining_quantity = $newRemaining;
        if ($newRemaining <= 0.0) {
            $this->status = self::STATUS_DEPLETED;
        }
        $this->save();
    }
}
