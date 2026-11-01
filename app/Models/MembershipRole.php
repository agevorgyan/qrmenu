<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MembershipRole extends Pivot
{
    protected $table = 'membership_roles';

    public $incrementing = false;

    protected $fillable = [
        'membership_id',
        'role_id',
        'created_at',
    ];

    public function membership()
    {
        return $this->belongsTo(VendorMembership::class, 'membership_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}
