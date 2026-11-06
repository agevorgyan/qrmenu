<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionOrder extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'vendor_id',
        'order_number',
        'recipe_id',
        'produced_ingredient_id',
        'source_warehouse_id',
        'destination_warehouse_id',
        'planned_quantity',
        'actual_quantity',
        'unit_id',
        'status',
        'planned_start_at',
        'actual_start_at',
        'completed_at',
        'produced_by',
        'batch_number',
        'expiry_date',
        'labor_cost',
        'overhead_cost',
        'material_cost',
        'total_cost',
        'unit_cost',
        'yield_variance_percent',
        'notes',
        'issue_transaction_id',
        'receipt_transaction_id',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:4',
        'actual_quantity' => 'decimal:4',
        'labor_cost' => 'decimal:4',
        'overhead_cost' => 'decimal:4',
        'material_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'yield_variance_percent' => 'decimal:2',
        'planned_start_at' => 'datetime',
        'actual_start_at' => 'datetime',
        'completed_at' => 'datetime',
        'expiry_date' => 'date',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function producedIngredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'produced_ingredient_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function producedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'produced_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionOrderItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(InventoryBatch::class);
    }

    public function issueTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'issue_transaction_id');
    }

    public function receiptTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'receipt_transaction_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function calculateYieldVariance(): ?float
    {
        if ($this->actual_quantity === null || (float) $this->planned_quantity <= 0.0) {
            return null;
        }

        $variance = (((float) $this->actual_quantity - (float) $this->planned_quantity) / (float) $this->planned_quantity) * 100.0;

        return round($variance, 2);
    }
}
