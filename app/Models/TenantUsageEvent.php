<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantUsageEvent extends Model
{
    use BelongsToVendor;
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'quota_key',
        'quantity',
        'idempotency_key',
        'recorded_at',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'recorded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
