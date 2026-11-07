<?php

namespace App\Domains\Wholesale\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleReturnItem extends Model
{
    use HasFactory;

    protected $table = 'wholesale_return_items';

    protected $fillable = [
        'return_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_amount',
        'is_restocked',
        'reason_detail',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'is_restocked' => 'boolean',
        ];
    }

    public function return(): BelongsTo
    {
        return $this->belongsTo(WholesaleReturn::class, 'return_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class, 'product_id');
    }
}
