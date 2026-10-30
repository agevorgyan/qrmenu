<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosLanSyncDispatch extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_lan_sync_dispatches';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'source_device_id',
        'target_device_id',
        'event_type',
        'payload',
        'status',
        'dispatched_at',
        'acknowledged_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'dispatched_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class, 'source_device_id');
    }

    public function targetDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class, 'target_device_id');
    }

    public function markAcknowledged(): self
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => Carbon::now(),
        ]);

        return $this;
    }

    public function markFailed(): self
    {
        $this->update([
            'status' => 'failed',
        ]);

        return $this;
    }
}
