<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PosDevice extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'pos_devices';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'name',
        'device_type',
        'pairing_code',
        'hardware_identifier',
        'ip_address',
        'is_active',
        'is_paired',
        'paired_at',
        'last_sync_at',
        'last_seen_at',
        'app_version',
        'registered_by_user_id',
        'metadata',
        'is_lan_hub',
        'lan_ip_address',
        'lan_port',
        'lan_auth_token',
        'lan_sync_priority',
        'last_lan_heartbeat_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_paired' => 'boolean',
            'is_lan_hub' => 'boolean',
            'lan_port' => 'integer',
            'lan_sync_priority' => 'integer',
            'last_lan_heartbeat_at' => 'datetime',
            'paired_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PosDevice $device) {
            if (empty($device->uuid)) {
                $device->uuid = (string) Str::uuid();
            }
            if (empty($device->pairing_code)) {
                $device->pairing_code = static::generatePairingCode();
            }
        });
    }

    /**
     * Generate an alphanumeric human-readable pairing code.
     */
    public static function generatePairingCode(): string
    {
        return strtoupper(Str::random(4).'-'.Str::random(4));
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(PosDeviceSession::class, 'device_id');
    }

    public function activeSession(): ?PosDeviceSession
    {
        return $this->sessions()
            ->where('is_active', true)
            ->where('expires_at', '>', Carbon::now())
            ->latest('last_activity_at')
            ->first();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pos_device_id');
    }

    public function operationLogs(): HasMany
    {
        return $this->hasMany(PosOperationLog::class, 'device_id');
    }

    /**
     * Check if device sent a heartbeat within the last 5 minutes.
     */
    public function isOnline(): bool
    {
        if (! $this->last_seen_at) {
            return false;
        }

        return $this->last_seen_at->gt(Carbon::now()->subMinutes(5));
    }

    /**
     * Touch device last seen and optionally last sync timestamp.
     */
    public function touchHeartbeat(?string $ipAddress = null, ?string $appVersion = null, bool $synced = false): void
    {
        $updates = [
            'last_seen_at' => Carbon::now(),
        ];

        if ($ipAddress) {
            $updates['ip_address'] = $ipAddress;
        }

        if ($appVersion) {
            $updates['app_version'] = $appVersion;
        }

        if ($synced) {
            $updates['last_sync_at'] = Carbon::now();
        }

        $this->update($updates);
    }

    public function markAsHub(?string $lanIp = null, int $lanPort = 8080): self
    {
        // Demote any other hub in this vendor/location
        static::where('vendor_id', $this->vendor_id)
            ->where('location_id', $this->location_id)
            ->where('id', '!=', $this->id)
            ->update(['is_lan_hub' => false]);

        $this->update([
            'is_lan_hub' => true,
            'lan_ip_address' => $lanIp ?: $this->lan_ip_address,
            'lan_port' => $lanPort,
            'lan_auth_token' => $this->lan_auth_token ?: Str::random(64),
            'last_lan_heartbeat_at' => Carbon::now(),
        ]);

        return $this;
    }

    public function stepDownHub(): self
    {
        $this->update(['is_lan_hub' => false]);

        return $this;
    }
}
