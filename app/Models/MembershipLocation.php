<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MembershipLocation extends Pivot
{
    protected $table = 'membership_locations';

    public $incrementing = false;

    protected $fillable = [
        'membership_id',
        'location_id',
        'created_at',
    ];

    public function membership()
    {
        return $this->belongsTo(VendorMembership::class, 'membership_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
