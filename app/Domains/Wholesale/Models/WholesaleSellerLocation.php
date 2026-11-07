<?php

namespace App\Domains\Wholesale\Models;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleSellerLocation extends Model
{
    use HasFactory;

    protected $table = 'wholesale_seller_locations';

    protected $fillable = [
        'seller_id',
        'name',
        'address',
        'city',
        'region',
        'warehouse_id',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(WholesaleSeller::class, 'seller_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}

