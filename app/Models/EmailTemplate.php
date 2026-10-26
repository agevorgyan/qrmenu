<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model representing safe structured email content and translations.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $category
 * @property string $subject
 * @property string|null $intro_text
 * @property string|null $cta_text
 * @property string|null $cta_url_template
 * @property string|null $footer_text
 * @property string|null $support_contact
 * @property string|null $branding_logo_url
 * @property string|null $branding_primary_color
 * @property array|null $allowed_variables
 * @property array|null $translations
 * @property bool $is_active
 * @property int $version
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class EmailTemplate extends Model
{
    use HasFactory;

    protected $table = 'email_templates';

    protected $fillable = [
        'key',
        'name',
        'category',
        'subject',
        'intro_text',
        'cta_text',
        'cta_url_template',
        'footer_text',
        'support_contact',
        'branding_logo_url',
        'branding_primary_color',
        'allowed_variables',
        'translations',
        'is_active',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'allowed_variables' => 'array',
            'translations' => 'array',
            'is_active' => 'boolean',
            'version' => 'integer',
        ];
    }

    /**
     * Safely replace allowed variable placeholders in text without executing arbitrary code.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderText(?string $content, array $variables = []): string
    {
        if (empty($content)) {
            return '';
        }

        $allowed = $this->allowed_variables ?? [];

        // Build search & replace pairs safely
        $search = [];
        $replace = [];

        foreach ($variables as $key => $val) {
            $normalizedKey = trim($key, '{} ');
            // Only substitute if key is in allowed variables list (or if list is empty as fallback)
            if (empty($allowed) || in_array($normalizedKey, $allowed, true)) {
                $search[] = "{{ {$normalizedKey} }}";
                $search[] = "{{{$normalizedKey}}}";
                $replace[] = e((string) $val);
                $replace[] = e((string) $val);
            }
        }

        return str_replace($search, $replace, $content);
    }

    /**
     * Resolve effective localized string for a field.
     */
    public function getLocalizedField(string $field, ?string $locale = null): ?string
    {
        if ($locale !== null) {
            $translations = $this->translations ?? [];

            if (isset($translations[$locale][$field]) && filled($translations[$locale][$field])) {
                return (string) $translations[$locale][$field];
            }
        }

        return (string) ($this->{$field} ?? '');
    }

    /**
     * Provide sample mock preview data for safe dashboard previewing.
     *
     * @return array<string, string>
     */
    public function getPreviewSampleData(): array
    {
        return match ($this->key) {
            'order_receipt' => [
                'customer_name' => 'Arman Gevorgyan',
                'vendor_name' => 'Lavash Restaurant Yerevan',
                'order_number' => 'ORD-9842',
                'order_type' => 'Dine-in (Table 12)',
                'total_amount' => '18,500 ֏',
                'support_email' => 'support@menu.elab.am',
                'site_name' => SystemSetting::getSiteName(),
            ],
            'welcome_verification' => [
                'user_name' => 'Davit Hakobyan',
                'vendor_name' => 'Cascade Bistro',
                'verification_url' => url('/email/verify/sample-token-12345'),
                'support_email' => 'support@menu.elab.am',
                'site_name' => SystemSetting::getSiteName(),
            ],
            'reset_password' => [
                'user_name' => 'Davit Hakobyan',
                'reset_url' => url('/password/reset/sample-token-67890'),
                'expire_minutes' => '60',
                'support_email' => 'support@menu.elab.am',
                'site_name' => SystemSetting::getSiteName(),
            ],
            'two_factor_code' => [
                'user_name' => 'Davit Hakobyan',
                'code' => '482910',
                'action_description' => 'Security Login',
                'expire_minutes' => '10',
                'support_email' => 'support@menu.elab.am',
                'site_name' => SystemSetting::getSiteName(),
            ],
            'reservation_request_received',
            'reservation_confirmed',
            'reservation_rejected',
            'reservation_modified',
            'reservation_cancelled',
            'reservation_reminder',
            'restaurant_reservation_received',
            'restaurant_reservation_awaiting_confirmation',
            'restaurant_reservation_action',
            'restaurant_reservation_modified',
            'restaurant_reservation_reminder' => [
                'customer_name' => 'Arman Gevorgyan',
                'restaurant_name' => 'Lavash Restaurant Yerevan',
                'reservation_reference' => 'RES-742910',
                'reservation_date' => now()->addDay()->format('Y-m-d'),
                'reservation_time' => '19:30',
                'guest_count' => '4',
                'table_name' => 'Table 14 (Terrace)',
                'reservation_status' => 'Confirmed',
                'preorder_summary' => '2x Ishkhan Fish, 1x Basturma Plate, 2x Fresh Lemonade',
                'reservation_total' => '24,500 ֏',
                'restaurant_address' => 'Tumanyan St 21, Yerevan',
                'restaurant_phone' => '+374 10 543210',
                'reservation_details_url' => url('/m/lavash-yerevan/reservation/RES-742910'),
                'cancellation_policy' => 'Free cancellation up to 2 hours before your scheduled arrival time.',
                'support_email' => 'support@menu.elab.am',
                'site_name' => SystemSetting::getSiteName(),
            ],
            'preorder_received',
            'preorder_confirmed',
            'preorder_status_changed',
            'preorder_cancelled',
            'restaurant_preorder_received',
            'restaurant_preorder_updated' => [
                'customer_name' => 'Arman Gevorgyan',
                'restaurant_name' => 'Lavash Restaurant Yerevan',
                'reservation_reference' => 'RES-742910',
                'reservation_date' => now()->addDay()->format('Y-m-d'),
                'reservation_time' => '19:30',
                'guest_count' => '4',
                'table_name' => 'Table 14 (Terrace)',
                'reservation_status' => 'Confirmed',
                'preorder_summary' => '2x Tolma in Grape Leaves, 1x Cheese Platter, 4x Armenian Mineral Water',
                'reservation_total' => '18,800 ֏',
                'restaurant_address' => 'Tumanyan St 21, Yerevan',
                'restaurant_phone' => '+374 10 543210',
                'reservation_details_url' => url('/m/lavash-yerevan/reservation/RES-742910'),
                'cancellation_policy' => 'Free cancellation up to 2 hours before your scheduled arrival time.',
                'support_email' => 'support@menu.elab.am',
                'site_name' => SystemSetting::getSiteName(),
            ],
            default => [
                'recipient_email' => 'admin@menu.elab.am',
                'timestamp' => now()->toDateTimeString(),
                'connection_name' => 'Primary Production SMTP',
                'site_name' => SystemSetting::getSiteName(),
            ],
        };
    }
}
