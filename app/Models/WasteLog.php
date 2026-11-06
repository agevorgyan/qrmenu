<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WasteLog extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    public const REASON_SPOILAGE = 'spoilage';

    public const REASON_EXPIRATION = 'expiration';

    public const REASON_PREP_MISTAKE = 'prep_mistake';

    public const REASON_BURN_SPILL = 'burn_spill';

    public const REASON_DAMAGE = 'damage';

    public const REASON_STORAGE_FAILURE = 'storage_failure';

    public const REASON_CUSTOMER_COMPLAINT = 'customer_complaint';

    public const REASON_OTHER = 'other';

    public const ACTION_DISCARDED = 'discarded';

    public const ACTION_STAFF_MEAL = 'staff_meal';

    public const ACTION_REPURPOSED = 'repurposed';

    public const ACTION_RETURNED = 'returned_to_vendor';

    protected $fillable = [
        'vendor_id',
        'waste_number',
        'warehouse_id',
        'ingredient_id',
        'batch_id',
        'quantity',
        'unit_id',
        'unit_cost',
        'total_cost',
        'reason',
        'action_taken',
        'logged_by',
        'authorized_by',
        'logged_at',
        'notes',
        'inventory_transaction_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'logged_at' => 'datetime',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'batch_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function inventoryTransaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }

    public static function generateWasteNumber(int $vendorId): string
    {
        $datePrefix = date('Ymd');
        $last = static::withoutGlobalScopes()
            ->where('vendor_id', $vendorId)
            ->where('waste_number', 'LIKE', "WST-{$datePrefix}-%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last && preg_match('/-(\d+)$/', $last->waste_number, $matches)) {
            $seq = ((int) $matches[1]) + 1;
        }

        return sprintf('WST-%s-%04d', $datePrefix, $seq);
    }
}
