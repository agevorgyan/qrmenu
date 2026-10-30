<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosSetting extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_settings';

    protected $fillable = [
        'vendor_id',
        'location_id',
        'enable_quick_pin',
        'require_table_selection',
        'auto_lock_seconds',
        'allow_manual_discounts',
        'max_discount_percent',
        'require_manager_approval_discount',
        'require_manager_approval_void',
        'default_order_type',
        'default_payment_method',
        'allow_offline_orders',
        'print_receipt_on_payment',
        'print_kitchen_ticket_on_order',
        'tax_rate',
        'tax_inclusive',
        'service_charge_percent',
        'custom_settings',
    ];

    protected function casts(): array
    {
        return [
            'enable_quick_pin' => 'boolean',
            'require_table_selection' => 'boolean',
            'auto_lock_seconds' => 'integer',
            'allow_manual_discounts' => 'boolean',
            'max_discount_percent' => 'decimal:2',
            'require_manager_approval_discount' => 'boolean',
            'require_manager_approval_void' => 'boolean',
            'allow_offline_orders' => 'boolean',
            'print_receipt_on_payment' => 'boolean',
            'print_kitchen_ticket_on_order' => 'boolean',
            'tax_rate' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'service_charge_percent' => 'decimal:2',
            'custom_settings' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get or create POS settings for a specific vendor and optional branch location.
     */
    public static function forLocation(Vendor $vendor, ?int $locationId = null): self
    {
        // 1. Try finding branch-specific setting
        if ($locationId) {
            $setting = static::where('vendor_id', $vendor->id)
                ->where('location_id', $locationId)
                ->first();

            if ($setting) {
                return $setting;
            }
        }

        // 2. Try finding vendor-level default (location_id is null)
        $defaultSetting = static::where('vendor_id', $vendor->id)
            ->whereNull('location_id')
            ->first();

        if ($defaultSetting) {
            return $defaultSetting;
        }

        // 3. Create initial vendor default settings
        return static::create([
            'vendor_id' => $vendor->id,
            'location_id' => null,
            'enable_quick_pin' => true,
            'require_table_selection' => true,
            'auto_lock_seconds' => 120,
            'allow_manual_discounts' => true,
            'max_discount_percent' => 20.00,
            'require_manager_approval_discount' => true,
            'require_manager_approval_void' => true,
            'default_order_type' => 'dine_in',
            'default_payment_method' => 'cash',
            'allow_offline_orders' => true,
            'print_receipt_on_payment' => true,
            'print_kitchen_ticket_on_order' => true,
            'tax_rate' => 0.00,
            'tax_inclusive' => true,
            'service_charge_percent' => 0.00,
            'custom_settings' => [],
        ]);
    }
}

