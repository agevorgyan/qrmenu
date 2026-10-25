<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'location_id',
        'name',
        'phone',
        'email',
        'birthdate',
        'address',
        'notes',
        'marketing_opt_in',
        'total_orders_count',
        'total_spent',
        'last_order_at',
    ];

    protected $casts = [
        'marketing_opt_in' => 'boolean',
        'total_spent' => 'decimal:2',
        'last_order_at' => 'datetime',
        'birthdate' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            if ($customer->location_id && $customer->vendor_id) {
                $location = Location::withoutGlobalScopes()->find($customer->location_id);
                if ($location && (int) $location->vendor_id !== (int) $customer->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor location assigned to customer.');
                }
            }
        });
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class)->latest();
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class)->latest('reservation_date');
    }

    public function eventInquiries()
    {
        return $this->hasMany(EventInquiry::class)->latest('event_date');
    }

    public function recalculateStats()
    {
        $this->total_orders_count = $this->orders()->count();
        $this->total_spent = $this->orders()->sum('total_amount');
        $this->last_order_at = $this->orders()->max('created_at');
        $this->save();
    }
}
