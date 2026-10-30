<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosLanPeer extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_lan_peers';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'pos_device_id',
        'device_name',
        'device_type',
        'role',
        'ip_address',
        'port',
        'status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'last_seen_at' => 'datetime',
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

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class, 'pos_device_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForLocation(Builder $query, ?int $locationId): Builder
    {
        if ($locationId) {
            return $query->where('location_id', $locationId);
        }

        return $query;
    }

    public function touchPeer(?string $ip = null, ?int $port = null, ?string $role = null): self
    {
        $updates = [
            'status' => 'active',
            'last_seen_at' => Carbon::now(),
        ];
        if ($ip) {
            $updates['ip_address'] = $ip;
        }
        if ($port) {
            $updates['port'] = $port;
        }
        if ($role) {
            $updates['role'] = $role;
        }

        $this->update($updates);

        return $this;
    }
}
