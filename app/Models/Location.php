<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Location extends Model
{
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'name',
        'slug',
        'address',
        'phone',
        'whatsapp_number',
        'telegram_chat_id',
        'wifi_ssid',
        'wifi_password',
        'working_hours',
        'opening_hours',
        'table_count',
        'allow_dine_in_orders',
        'allow_whatsapp_orders',
        'minimum_order_amount',
        'is_active',
        'dine_in_schedule_enabled',
        'dine_in_start_time',
        'dine_in_end_time',
        'dine_in_days',
        'delivery_schedule_enabled',
        'delivery_start_time',
        'delivery_end_time',
        'delivery_days',
        'takeaway_schedule_enabled',
        'takeaway_start_time',
        'takeaway_end_time',
        'takeaway_days',
        'closing_warning_enabled',
        'closing_warning_minutes',
        'closing_warning_message',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'allow_dine_in_orders' => 'boolean',
        'allow_whatsapp_orders' => 'boolean',
        'is_active' => 'boolean',
        'dine_in_schedule_enabled' => 'boolean',
        'dine_in_days' => 'array',
        'delivery_schedule_enabled' => 'boolean',
        'delivery_days' => 'array',
        'takeaway_schedule_enabled' => 'boolean',
        'takeaway_days' => 'array',
        'closing_warning_enabled' => 'boolean',
        'closing_warning_minutes' => 'integer',
    ];

    public function resolveOperatingSchedule(string $channel, ?Carbon $now = null, ?string $lang = null): array
    {
        return $this->vendor->resolveOperatingSchedule($channel, $this, $now, $lang);
    }

    public function isChannelOpen(string $channel, ?Carbon $now = null): bool
    {
        return $this->vendor->isChannelOpen($channel, $this, $now);
    }

    public function getClosingNotice(string $channel, ?Carbon $now = null, ?string $lang = null): ?array
    {
        return $this->vendor->getClosingNotice($channel, $this, $now, $lang);
    }

    /**
     * Transparently decrypt Wi-Fi password when retrieved, falling back to plaintext for legacy rows.
     */
    public function getWifiPasswordAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * Transparently encrypt Wi-Fi password when stored at rest in the database.
     */
    public function setWifiPasswordAttribute(?string $value): void
    {
        $this->attributes['wifi_password'] = ($value !== null && $value !== '')
            ? Crypt::encryptString($value)
            : null;
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function productOverrides()
    {
        return $this->hasMany(LocationProductOverride::class);
    }
}
