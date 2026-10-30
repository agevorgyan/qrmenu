<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosDeviceSession extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_device_sessions';

    protected $fillable = [
        'device_id',
        'user_id',
        'vendor_id',
        'location_id',
        'session_token',
        'mode',
        'ip_address',
        'user_agent',
        'is_active',
        'locked_at',
        'expires_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'locked_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PosDeviceSession $session) {
            if (empty($session->session_token)) {
                $session->session_token = Str::random(64);
            }
            if (empty($session->expires_at)) {
                $session->expires_at = Carbon::now()->addHours(12);
            }
            if (empty($session->last_activity_at)) {
                $session->last_activity_at = Carbon::now();
            }
        });
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class, 'device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isLocked(): bool
    {
        return ! is_null($this->locked_at);
    }

    public function lock(): void
    {
        $this->update(['locked_at' => Carbon::now()]);
    }

    public function unlock(): void
    {
        $this->update(['locked_at' => null, 'last_activity_at' => Carbon::now()]);
    }

    public function touchActivity(): void
    {
        $this->update([
            'last_activity_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addHours(12),
        ]);
    }
}

