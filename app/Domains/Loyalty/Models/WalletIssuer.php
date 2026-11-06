<?php

namespace App\Domains\Loyalty\Models;

use App\Domains\Loyalty\Enums\WalletIssuerMode;
use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use App\Models\VendorCredential;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletIssuer extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'wallet_issuers';

    protected $fillable = [
        'vendor_id',
        'issuer_mode',
        'apple_pass_type_id',
        'apple_team_id',
        'apple_cert_credential_id',
        'apple_key_credential_id',
        'apple_cert_expires_at',
        'apple_status',
        'google_issuer_id',
        'google_service_account_credential_id',
        'google_class_id',
        'google_status',
    ];

    protected function casts(): array
    {
        return [
            'issuer_mode' => WalletIssuerMode::class,
            'apple_cert_expires_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function appleCertCredential(): BelongsTo
    {
        return $this->belongsTo(VendorCredential::class, 'apple_cert_credential_id');
    }

    public function appleKeyCredential(): BelongsTo
    {
        return $this->belongsTo(VendorCredential::class, 'apple_key_credential_id');
    }

    public function googleServiceAccountCredential(): BelongsTo
    {
        return $this->belongsTo(VendorCredential::class, 'google_service_account_credential_id');
    }

    public function isAppleReady(): bool
    {
        return $this->apple_status === 'active';
    }

    public function isGoogleReady(): bool
    {
        return $this->google_status === 'active';
    }
}
