<?php

namespace App\Domains\Wholesale\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WholesalePriceList extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'wholesale_price_lists';

    protected $fillable = [
        'vendor_id',
        'name',
        'code',
        'currency',
        'effective_from',
        'effective_to',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WholesalePriceListItem::class, 'price_list_id');
    }

    public function isCurrentlyEffective(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $today = now()->startOfDay();

        if ($this->effective_from && $today->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_to && $today->gt($this->effective_to)) {
            return false;
        }

        return true;
    }
}
