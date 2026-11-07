<?php

namespace App\Domains\Wholesale\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleProductPackaging extends Model
{
    use HasFactory;

    protected $table = 'wholesale_product_packagings';

    protected $fillable = [
        'product_id',
        'packaging_type',
        'packaging_barcode',
        'units_count',
        'gross_weight_kg',
        'dimensions',
    ];

    protected function casts(): array
    {
        return [
            'units_count' => 'decimal:4',
            'gross_weight_kg' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class, 'product_id');
    }
}

