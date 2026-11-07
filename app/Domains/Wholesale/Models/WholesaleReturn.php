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

class WholesaleReturn extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'wholesale_returns';

    protected $fillable = [
        'vendor_id',
        'customer_id',
        'order_id',
        'sale_id',
        'warehouse_id',
        'return_number',
        'return_date',
        'status',
        'action_type',
        'total_return_amount',
        'reason',
        'notes',
        'processed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'total_return_amount' => 'decimal:4',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WholesaleCustomer::class, 'customer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WholesaleOrder::class, 'order_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(WholesaleSale::class, 'sale_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WholesaleReturnItem::class, 'return_id');
    }

    public function processedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }
}

