<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Customer;
use App\Models\Traits\BelongsToVendor;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyAuditLog extends Model
{
    use BelongsToVendor, HasFactory;

    public $timestamps = false;

    protected $table = 'loyalty_audit_logs';

    protected $fillable = [
        'vendor_id',
        'staff_id',
        'customer_id',
        'action',
        'details',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LoyaltyAuditLog $log) {
            if (empty($log->created_at)) {
                $log->created_at = now();
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
