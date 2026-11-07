<?php

namespace App\Domains\Wholesale\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WholesaleDelivery extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'wholesale_deliveries';

    protected $fillable = [
        'vendor_id',
        'order_id',
        'delivery_number',
        'warehouse_id',
        'destination_address_id',
        'driver_name',
        'driver_phone',
        'vehicle_number',
        'status',
        'dispatched_at',
        'delivered_at',
        'received_by_name',
        'proof_of_delivery_note',
        'failure_reason',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
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

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function destinationAddress(): BelongsTo
    {
        return $this->belongsTo(WholesaleCustomerAddress::class, 'destination_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WholesaleDeliveryItem::class, 'delivery_id');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
