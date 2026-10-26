<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TipRatingSetting extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'tip_rating_settings';

    protected $fillable = [
        'vendor_id',
        'is_enabled',
        'tips_enabled',
        'ratings_enabled',
        'tip_percentages',
        'fixed_tip_amounts',
        'allow_custom_tip',
        'default_tip_selection',
        'calculation_base',
        'applicable_order_types',
        'min_tip_amount',
        'max_tip_amount',
        'supported_payment_methods',
        'allow_cash_tips',
        'allocation_policy',
        'rating_scale',
        'allow_written_feedback',
        'require_written_feedback',
        'is_anonymous_by_default',
        'allow_customer_name',
        'feedback_categories',
        'allow_waiter_selection',
        'submission_window_hours',
        'duplicate_policy',
        'auto_publish_ratings',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'tips_enabled' => 'boolean',
        'ratings_enabled' => 'boolean',
        'tip_percentages' => 'array',
        'fixed_tip_amounts' => 'array',
        'allow_custom_tip' => 'boolean',
        'applicable_order_types' => 'array',
        'min_tip_amount' => 'decimal:2',
        'max_tip_amount' => 'decimal:2',
        'supported_payment_methods' => 'array',
        'allow_cash_tips' => 'boolean',
        'rating_scale' => 'integer',
        'allow_written_feedback' => 'boolean',
        'require_written_feedback' => 'boolean',
        'is_anonymous_by_default' => 'boolean',
        'allow_customer_name' => 'boolean',
        'feedback_categories' => 'array',
        'allow_waiter_selection' => 'boolean',
        'submission_window_hours' => 'integer',
        'auto_publish_ratings' => 'boolean',
    ];

    /**
     * Default feedback categories.
     *
     * @return array<int, string>
     */
    public static function defaultFeedbackCategories(): array
    {
        return [
            'Food Quality',
            'Speed of Service',
            'Staff Friendliness',
            'Cleanliness',
            'Atmosphere',
        ];
    }

    /**
     * Default tip percentage options.
     *
     * @return array<int, int>
     */
    public static function defaultTipPercentages(): array
    {
        return [5, 10, 15, 20];
    }

    /**
     * Default fixed tip amounts.
     *
     * @return array<int, int>
     */
    public static function defaultFixedTipAmounts(): array
    {
        return [200, 500, 1000, 2000];
    }

    /**
     * Get or create active settings for a vendor.
     */
    public static function forVendor(Vendor $vendor): self
    {
        return static::withoutGlobalScopes()
            ->firstOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'is_enabled' => true,
                    'tips_enabled' => true,
                    'ratings_enabled' => true,
                    'tip_percentages' => static::defaultTipPercentages(),
                    'fixed_tip_amounts' => static::defaultFixedTipAmounts(),
                    'allow_custom_tip' => true,
                    'default_tip_selection' => 'none',
                    'calculation_base' => 'subtotal',
                    'applicable_order_types' => ['dine_in', 'takeaway', 'delivery'],
                    'min_tip_amount' => 0.00,
                    'max_tip_amount' => 50000.00,
                    'supported_payment_methods' => ['idram', 'telcell', 'arca', 'stripe', 'cash', 'pos_terminal'],
                    'allow_cash_tips' => true,
                    'allocation_policy' => 'pool',
                    'rating_scale' => 5,
                    'allow_written_feedback' => true,
                    'require_written_feedback' => false,
                    'is_anonymous_by_default' => true,
                    'allow_customer_name' => true,
                    'feedback_categories' => static::defaultFeedbackCategories(),
                    'allow_waiter_selection' => true,
                    'submission_window_hours' => 24,
                    'duplicate_policy' => 'once_per_order',
                    'auto_publish_ratings' => true,
                ]
            );
    }

    /**
     * Check if tip functionality is active for the given order type.
     */
    public function isTipActiveFor(?string $orderType = null): bool
    {
        if (! $this->is_enabled || ! $this->tips_enabled) {
            return false;
        }

        if ($orderType === null) {
            return true;
        }

        $applicable = is_array($this->applicable_order_types)
            ? $this->applicable_order_types
            : ['dine_in', 'takeaway', 'delivery'];

        return in_array($orderType, $applicable, true);
    }

    /**
     * Check if rating functionality is active.
     */
    public function isRatingActive(): bool
    {
        return $this->is_enabled && $this->ratings_enabled;
    }

    /**
     * Backward-compatible alias for allocation_policy.
     */
    public function getTipRecipientPolicyAttribute(): string
    {
        return $this->allocation_policy ?? 'pool';
    }

    /**
     * Backward-compatible alias for !require_written_feedback.
     */
    public function getIsWrittenFeedbackOptionalAttribute(): bool
    {
        return ! (bool) ($this->require_written_feedback ?? false);
    }

    /**
     * Backward-compatible alias for is_anonymous_by_default.
     */
    public function getAllowAnonymousRatingsAttribute(): bool
    {
        return (bool) ($this->is_anonymous_by_default ?? true);
    }

    /**
     * Fallback for trigger point.
     */
    public function getTriggerPointAttribute(): string
    {
        return 'after_order';
    }

    /**
     * Belongs to vendor relationship.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
