<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HaccpTemperatureLog extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'haccp_temperature_logs';

    protected $fillable = [
        'vendor_id',
        'ccp_id',
        'warehouse_id',
        'ingredient_id',
        'batch_id',
        'equipment_name',
        'measured_value',
        'unit',
        'is_breached',
        'corrective_action_taken',
        'logged_by',
        'logged_at',
        'notes',
    ];

    protected $casts = [
        'measured_value' => 'decimal:2',
        'is_breached' => 'boolean',
        'logged_at' => 'datetime',
    ];

    public function ccp(): BelongsTo
    {
        return $this->belongsTo(HaccpCcp::class, 'ccp_id');
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

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
