<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Customer;
use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WalletPass extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'wallet_passes';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'customer_id',
        'serial_number',
        'authentication_token',
        'apple_push_token',
        'google_object_id',
        'pass_type_identifier',
        'status',
        'last_pushed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_pushed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WalletPass $pass) {
            if (empty($pass->uuid)) {
                $pass->uuid = (string) Str::uuid();
            }
            if (empty($pass->serial_number)) {
                $pass->serial_number = 'PASS-'.strtoupper(Str::random(12));
            }
            if (empty($pass->authentication_token)) {
                $pass->authentication_token = bin2hex(random_bytes(16));
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function deviceRegistrations(): HasMany
    {
        return $this->hasMany(WalletDeviceRegistration::class, 'pass_id');
    }

    public function updateJobs(): HasMany
    {
        return $this->hasMany(WalletUpdateJob::class, 'pass_id');
    }
}
