<?php

namespace App\Domains\Wholesale\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesalePaymentAllocation extends Model
{
    use HasFactory;

    protected $table = 'wholesale_payment_allocations';

    protected $fillable = [
        'payment_id',
        'sale_id',
        'amount',
        'allocated_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'allocated_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(WholesalePayment::class, 'payment_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(WholesaleSale::class, 'sale_id');
    }
}
