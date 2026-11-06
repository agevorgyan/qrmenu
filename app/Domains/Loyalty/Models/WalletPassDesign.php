<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletPassDesign extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'wallet_pass_designs';

    protected $fillable = [
        'vendor_id',
        'background_color',
        'foreground_color',
        'label_color',
        'logo_url',
        'strip_image_url',
        'icon_url',
        'card_title',
        'header_field',
        'primary_field',
        'secondary_fields',
        'auxiliary_fields',
        'back_fields',
        'barcode_type',
        'is_published',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'secondary_fields' => 'array',
            'auxiliary_fields' => 'array',
            'back_fields' => 'array',
            'is_published' => 'boolean',
            'version' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
