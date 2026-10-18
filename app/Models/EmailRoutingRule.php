<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model representing category-based email routing to senders and connections.
 *
 * @property int $id
 * @property string $category
 * @property string $name
 * @property string|null $description
 * @property int|null $sender_identity_id
 * @property int|null $email_connection_id
 * @property bool $is_active
 * @property int $priority
 * @property string $fallback_behavior
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read EmailSenderIdentity|null $sender
 * @property-read EmailConnection|null $connection
 */
class EmailRoutingRule extends Model
{
    use HasFactory;

    protected $table = 'email_routing_rules';

    public const CATEGORY_REGISTRATION = 'registration';

    public const CATEGORY_VERIFICATION = 'verification';

    public const CATEGORY_PASSWORD_RESET = 'password_reset';

    public const CATEGORY_TWO_FACTOR = 'two_factor';

    public const CATEGORY_SECURITY_ALERT = 'security_alert';

    public const CATEGORY_VENDOR_ONBOARDING = 'vendor_onboarding';

    public const CATEGORY_ORDER_RECEIPT = 'order_receipt';

    public const CATEGORY_RESERVATIONS = 'reservations';

    public const CATEGORY_PREORDERS = 'preorders';

    public const CATEGORY_SUBSCRIPTION_BILLING = 'subscription_billing';

    public const CATEGORY_ADMIN_ALERT = 'admin_alert';

    public const CATEGORY_MARKETING = 'marketing';

    public const CATEGORY_SYSTEM_TEST = 'system_test';

    protected $fillable = [
        'category',
        'name',
        'description',
        'sender_identity_id',
        'email_connection_id',
        'is_active',
        'priority',
        'fallback_behavior',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(EmailSenderIdentity::class, 'sender_identity_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(EmailConnection::class, 'email_connection_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get all supported email routing categories with localized labels.
     *
     * @return array<string, array{label: string, description: string}>
     */
    public static function categories(): array
    {
        return [
            self::CATEGORY_REGISTRATION => [
                'label' => 'Account Registration',
                'description' => 'User registration and onboarding emails',
            ],
            self::CATEGORY_VERIFICATION => [
                'label' => 'Email Verification',
                'description' => 'Email address confirmation links and tokens',
            ],
            self::CATEGORY_PASSWORD_RESET => [
                'label' => 'Password Reset',
                'description' => 'Password recovery requests and links',
            ],
            self::CATEGORY_TWO_FACTOR => [
                'label' => 'Two-Factor Authentication (2FA)',
                'description' => 'One-time security verification codes',
            ],
            self::CATEGORY_SECURITY_ALERT => [
                'label' => 'Security Notifications',
                'description' => 'Password changes, role changes, and security warnings',
            ],
            self::CATEGORY_VENDOR_ONBOARDING => [
                'label' => 'Vendor Welcome & Onboarding',
                'description' => 'New restaurant/partner welcome notices',
            ],
            self::CATEGORY_ORDER_RECEIPT => [
                'label' => 'Order Confirmations & Receipts',
                'description' => 'Customer receipts and kitchen order confirmations',
            ],
            self::CATEGORY_RESERVATIONS => [
                'label' => 'Table Reservations & Reminders',
                'description' => 'Customer reservation confirmations, status updates, and reminders',
            ],
            self::CATEGORY_PREORDERS => [
                'label' => 'Reservation Pre-Orders',
                'description' => 'Pre-order confirmations, preparation updates, and changes',
            ],
            self::CATEGORY_SUBSCRIPTION_BILLING => [
                'label' => 'Subscription & Billing',
                'description' => 'Invoices, renewals, trial warnings, and payments',
            ],
            self::CATEGORY_ADMIN_ALERT => [
                'label' => 'Platform Admin Notifications',
                'description' => 'System alerts, high queue backlog notices, and errors',
            ],
            self::CATEGORY_MARKETING => [
                'label' => 'Marketing & Product News',
                'description' => 'Promotional emails and feature announcements',
            ],
            self::CATEGORY_SYSTEM_TEST => [
                'label' => 'System Connection Tests',
                'description' => 'Automated and manual test email dispatches',
            ],
        ];
    }
}
