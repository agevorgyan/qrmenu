<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosSyncConflict extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_sync_conflicts';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'pos_sync_batch_id',
        'pos_device_id',
        'client_sync_id',
        'entity_type',
        'conflict_type',
        'client_data',
        'server_data',
        'resolution_strategy',
        'is_resolved',
        'resolved_by_user_id',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'client_data' => 'array',
            'server_data' => 'array',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PosSyncBatch::class, 'pos_sync_batch_id');
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class, 'pos_device_id');
    }

    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function markResolved(User $user, string $notes = ''): self
    {
        $this->update([
            'is_resolved' => true,
            'resolved_by_user_id' => $user->id,
            'resolved_at' => Carbon::now(),
            'resolution_notes' => $notes,
        ]);

        return $this;
    }
}
