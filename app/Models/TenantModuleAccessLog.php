<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModuleAccessLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'tenant_module_access_logs';

    protected $fillable = [
        'tenant_module_access_id',
        'vendor_id',
        'module_key',
        'action', // granted, suspended, revoked, renewed, expired
        'acted_by',
        'previous_state',
        'new_state',
        'reason',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_state' => 'array',
            'new_state' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function access(): BelongsTo
    {
        return $this->belongsTo(TenantModuleAccess::class, 'tenant_module_access_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function actedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}

