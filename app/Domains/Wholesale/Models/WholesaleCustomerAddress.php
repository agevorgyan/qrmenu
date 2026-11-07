<?php

namespace App\Domains\Wholesale\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleCustomerAddress extends Model
{
    use HasFactory;

    protected $table = 'wholesale_customer_addresses';

    protected $fillable = [
        'customer_id',
        'name',
        'address_line1',
        'address_line2',
        'city',
        'postal_code',
        'contact_name',
        'contact_phone',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WholesaleCustomer::class, 'customer_id');
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([$this->city, $this->address_line1, $this->address_line2]);

        return implode(', ', $parts);
    }
}
