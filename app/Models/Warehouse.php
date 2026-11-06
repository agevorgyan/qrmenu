<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    public const TYPE_MAIN = 'main';

    public const TYPE_KITCHEN = 'kitchen';

    public const TYPE_COLD_ROOM = 'cold_room';

    public const TYPE_FREEZER = 'freezer';

    public const TYPE_PRODUCTION = 'production';

    public const TYPE_FINISHED_GOODS = 'finished_goods';

    public const TYPE_BAR = 'bar';

    protected $fillable = [
        'vendor_id',
        'location_id',
        'name',
        'code',
        'address',
        'type',
        'responsible_user_id',
        'storage_conditions',
        'temperature_min',
        'temperature_max',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'temperature_min' => 'decimal:2',
        'temperature_max' => 'decimal:2',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class, 'warehouse_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'warehouse_id');
    }

    public function targetTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'target_warehouse_id');
    }
}
