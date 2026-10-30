<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PosSyncBatch extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_sync_batches';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'pos_device_id',
        'user_id',
        'batch_id',
        'status',
        'total_operations',
        'synced_operations',
        'conflict_operations',
        'client_started_at',
        'client_ended_at',
        'server_processed_at',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'total_operations' => 'integer',
            'synced_operations' => 'integer',
            'conflict_operations' => 'integer',
            'client_started_at' => 'datetime',
            'client_ended_at' => 'datetime',
            'server_processed_at' => 'datetime',
            'summary' => 'array',
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

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class, 'pos_device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(PosSyncOperation::class, 'pos_sync_batch_id');
    }

    public function conflicts(): HasMany
    {
        return $this->hasMany(PosSyncConflict::class, 'pos_sync_batch_id');
    }
}
