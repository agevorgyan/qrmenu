<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SplitBillSetting extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'split_bill_settings';

    protected $fillable = [
        'vendor_id',
        'is_enabled',
        'allow_equal_split',
        'allow_item_split',
        'allow_custom_amount',
        'allow_share_link',
        'max_participants',
        'require_staff_approval',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'allow_equal_split' => 'boolean',
        'allow_item_split' => 'boolean',
        'allow_custom_amount' => 'boolean',
        'allow_share_link' => 'boolean',
        'max_participants' => 'integer',
        'require_staff_approval' => 'boolean',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Get or create singleton settings for a Vendor.
     */
    public static function forVendor(Vendor $vendor): static
    {
        return static::withoutGlobalScopes()->firstOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'is_enabled' => false,
                'allow_equal_split' => true,
                'allow_item_split' => true,
                'allow_custom_amount' => true,
                'allow_share_link' => true,
                'max_participants' => 10,
                'require_staff_approval' => false,
            ]
        );
    }
}
