<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletDeviceRegistration extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'wallet_device_registrations';

    protected $fillable = [
        'vendor_id',
        'pass_id',
        'device_library_identifier',
        'push_token',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(WalletPass::class, 'pass_id');
    }
}
