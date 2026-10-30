<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosSyncOperation extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_sync_operations';

    protected $fillable = [
        'uuid',
        'pos_sync_batch_id',
        'vendor_id',
        'pos_device_id',
        'client_sync_id',
        'entity_type',
        'action',
        'client_timestamp',
        'payload',
        'status',
        'server_entity_id',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'client_timestamp' => 'datetime',
            'payload' => 'array',
            'server_entity_id' => 'integer',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PosSyncBatch::class, 'pos_sync_batch_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class, 'pos_device_id');
    }
}
