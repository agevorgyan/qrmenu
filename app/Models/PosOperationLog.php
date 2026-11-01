<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOperationLog extends Model
{
    use BelongsToVendor, HasFactory;

    public $timestamps = false;

    protected $table = 'pos_operation_logs';

    protected $fillable = [
        'vendor_id',
        'location_id',
        'user_id',
        'device_id',
        'action',
        'entity_type',
        'entity_id',
        'details',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class, 'device_id');
    }

    /**
     * Record an immutable audit log entry for a POS operation.
     */
    public static function log(
        Vendor $vendor,
        string $action,
        ?User $user = null,
        ?PosDevice $device = null,
        ?Model $entity = null,
        ?array $details = null,
        ?int $locationId = null,
        ?string $ipAddress = null
    ): self {
        return static::create([
            'vendor_id' => $vendor->id,
            'location_id' => $locationId ?? $device?->location_id ?? $user?->location_id,
            'user_id' => $user?->id,
            'device_id' => $device?->id,
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey(),
            'details' => $details,
            'ip_address' => $ipAddress ?? request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
