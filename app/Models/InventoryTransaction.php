<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransaction extends Model
{
    use BelongsToVendor, HasFactory;

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_ISSUE = 'issue';

    public const TYPE_TRANSFER = 'transfer';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_COUNT = 'count';

    public const TYPE_RESERVATION = 'reservation';

    public const TYPE_RELEASE = 'release';

    public const TYPE_WASTE = 'waste';

    public const TYPE_PRODUCTION_CONSUMPTION = 'production_consumption';

    public const TYPE_PRODUCTION_RECEIPT = 'production_receipt';

    public const TYPE_ORDER_CONSUMPTION = 'order_consumption';

    public const TYPE_ORDER_REFUND = 'order_refund';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'vendor_id',
        'transaction_number',
        'warehouse_id',
        'target_warehouse_id',
        'type',
        'status',
        'date',
        'reference_type',
        'reference_id',
        'user_id',
        'approved_by',
        'notes',
        'total_amount',
        'idempotency_key',
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function targetWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'target_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryTransactionItem::class, 'transaction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Generate unique transaction code.
     */
    public static function generateTransactionNumber(string $prefix = 'TX'): string
    {
        return sprintf('%s-%s-%04d', strtoupper($prefix), now()->format('Ymd'), random_int(1000, 9999));
    }
}
