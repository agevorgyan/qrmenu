<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Model representing an Event Planning Inquiry.
 *
 * @property int $id
 * @property string $uuid
 * @property string $reference
 * @property int $vendor_id
 * @property int|null $location_id
 * @property int|null $assigned_user_id
 * @property string $event_type
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $customer_email
 * @property string $preferred_contact_method
 * @property string $event_date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property float|null $estimated_duration_hours
 * @property int $guest_count
 * @property float|null $budget
 * @property string|null $budget_range
 * @property string $budget_currency
 * @property string $venue_arrangement
 * @property string|null $food_beverage_preferences
 * @property array|null $additional_services
 * @property string|null $comments
 * @property string $status
 * @property float|null $quoted_price
 * @property string|null $cancellation_reason
 * @property string $locale
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Vendor|null $vendor
 * @property-read Location|null $location
 * @property-read User|null $assignedUser
 */
class EventInquiry extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'event_inquiries';

    // Status Constants
    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_PREPARING_OFFER = 'preparing_offer';

    public const STATUS_OFFER_SENT = 'offer_sent';

    public const STATUS_NEGOTIATION = 'negotiation';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    // Venue Arrangement Constants
    public const VENUE_INDOOR = 'indoor';

    public const VENUE_OUTDOOR = 'outdoor';

    public const VENUE_PRIVATE_ROOM = 'private_room';

    public const VENUE_NO_PREFERENCE = 'no_preference';

    // Contact Method Constants
    public const CONTACT_PHONE = 'phone';

    public const CONTACT_WHATSAPP = 'whatsapp';

    public const CONTACT_EMAIL = 'email';

    protected $fillable = [
        'uuid',
        'reference',
        'vendor_id',
        'location_id',
        'customer_id',
        'assigned_user_id',
        'event_type',
        'customer_name',
        'customer_phone',
        'customer_email',
        'preferred_contact_method',
        'event_date',
        'start_time',
        'end_time',
        'estimated_duration_hours',
        'guest_count',
        'budget',
        'budget_range',
        'budget_currency',
        'venue_arrangement',
        'food_beverage_preferences',
        'additional_services',
        'comments',
        'status',
        'quoted_price',
        'cancellation_reason',
        'locale',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'budget' => 'decimal:2',
            'quoted_price' => 'decimal:2',
            'estimated_duration_hours' => 'decimal:1',
            'additional_services' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventInquiry $inquiry) {
            if (empty($inquiry->uuid)) {
                $inquiry->uuid = (string) Str::uuid();
            }

            if (empty($inquiry->reference)) {
                $inquiry->reference = 'EV-'.strtoupper(Str::random(6));
            }

            if (empty($inquiry->locale)) {
                $inquiry->locale = session('app_locale') ?: (app()->getLocale() ?: 'hy');
            }
        });
    }

    /**
     * Get all predefined event inquiry statuses.
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_NEW => __('New'),
            self::STATUS_CONTACTED => __('Contacted'),
            self::STATUS_PREPARING_OFFER => __('Preparing Offer'),
            self::STATUS_OFFER_SENT => __('Offer Sent'),
            self::STATUS_NEGOTIATION => __('Negotiation'),
            self::STATUS_CONFIRMED => __('Confirmed'),
            self::STATUS_COMPLETED => __('Completed'),
            self::STATUS_DECLINED => __('Declined'),
            self::STATUS_CANCELLED => __('Cancelled'),
        ];
    }

    /**
     * Relationship: Vendor
     */
    public function getBudgetCurrencyAttribute(?string $value): string
    {
        $val = trim((string) ($value ?: '֏'));

        return strtoupper($val) === 'AMD' ? '֏' : $val;
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Relationship: Location / Branch
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Relationship: Customer (CRM)
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relationship: Assigned Team Member
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * Relationship: Internal notes
     */
    public function notes(): HasMany
    {
        return $this->hasMany(EventInquiryNote::class)->orderBy('created_at', 'desc');
    }

    /**
     * Relationship: Chronological Activity History
     */
    public function activities(): HasMany
    {
        return $this->hasMany(EventInquiryActivity::class)->orderBy('created_at', 'desc');
    }

    /**
     * Record an audit activity for this event inquiry.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordActivity(string $action, string $description, ?array $metadata = null, ?User $user = null): EventInquiryActivity
    {
        return $this->activities()->create([
            'vendor_id' => $this->vendor_id,
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Scope: search by customer name, phone, email, or reference.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('customer_name', 'ilike', "%{$term}%")
                ->orWhere('customer_phone', 'like', "%{$term}%")
                ->orWhere('customer_email', 'ilike', "%{$term}%")
                ->orWhere('reference', 'ilike', "%{$term}%");
        });
    }

    /**
     * Scope: filter by status.
     */
    public function scopeFilterStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || $status === 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }

    /**
     * Scope: filter by event type.
     */
    public function scopeFilterEventType(Builder $query, ?string $eventType): Builder
    {
        if (blank($eventType) || $eventType === 'all') {
            return $query;
        }

        return $query->where('event_type', $eventType);
    }

    /**
     * Scope: filter by date range.
     */
    public function scopeFilterDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if (filled($from)) {
            $query->where('event_date', '>=', $from);
        }

        if (filled($to)) {
            $query->where('event_date', '<=', $to);
        }

        return $query;
    }

    /**
     * Scope: filter by guest count range.
     */
    public function scopeFilterGuestCount(Builder $query, ?int $min, ?int $max): Builder
    {
        if ($min !== null && $min > 0) {
            $query->where('guest_count', '>=', $min);
        }

        if ($max !== null && $max > 0) {
            $query->where('guest_count', '<=', $max);
        }

        return $query;
    }

    /**
     * Get localized status label.
     */
    public function getStatusLabel(?string $locale = null): string
    {
        $loc = $locale ?: app()->getLocale();

        return match ($this->status) {
            self::STATUS_NEW => __('New', [], $loc),
            self::STATUS_CONTACTED => __('Contacted', [], $loc),
            self::STATUS_PREPARING_OFFER => __('Preparing Offer', [], $loc),
            self::STATUS_OFFER_SENT => __('Offer Sent', [], $loc),
            self::STATUS_NEGOTIATION => __('Negotiation', [], $loc),
            self::STATUS_CONFIRMED => __('Confirmed', [], $loc),
            self::STATUS_COMPLETED => __('Completed', [], $loc),
            self::STATUS_DECLINED => __('Declined', [], $loc),
            self::STATUS_CANCELLED => __('Cancelled', [], $loc),
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    /**
     * Get status badge styling.
     */
    public function getStatusBadgeColors(): array
    {
        return match ($this->status) {
            self::STATUS_NEW => [
                'bg' => 'rgba(59, 130, 246, 0.12)',
                'color' => '#3b82f6',
                'border' => 'rgba(59, 130, 246, 0.3)',
                'icon' => 'fa-solid fa-sparkles',
            ],
            self::STATUS_CONTACTED => [
                'bg' => 'rgba(14, 165, 233, 0.12)',
                'color' => '#0284c7',
                'border' => 'rgba(14, 165, 233, 0.3)',
                'icon' => 'fa-solid fa-phone-volume',
            ],
            self::STATUS_PREPARING_OFFER => [
                'bg' => 'rgba(245, 158, 11, 0.12)',
                'color' => '#d97706',
                'border' => 'rgba(245, 158, 11, 0.3)',
                'icon' => 'fa-solid fa-file-pen',
            ],
            self::STATUS_OFFER_SENT => [
                'bg' => 'rgba(168, 85, 247, 0.12)',
                'color' => '#9333ea',
                'border' => 'rgba(168, 85, 247, 0.3)',
                'icon' => 'fa-solid fa-paper-plane',
            ],
            self::STATUS_NEGOTIATION => [
                'bg' => 'rgba(236, 72, 153, 0.12)',
                'color' => '#db2777',
                'border' => 'rgba(236, 72, 153, 0.3)',
                'icon' => 'fa-solid fa-comments-dollar',
            ],
            self::STATUS_CONFIRMED => [
                'bg' => 'rgba(16, 185, 129, 0.12)',
                'color' => '#059669',
                'border' => 'rgba(16, 185, 129, 0.3)',
                'icon' => 'fa-solid fa-circle-check',
            ],
            self::STATUS_COMPLETED => [
                'bg' => 'rgba(100, 116, 139, 0.12)',
                'color' => '#475569',
                'border' => 'rgba(100, 116, 139, 0.3)',
                'icon' => 'fa-solid fa-flag-checkered',
            ],
            self::STATUS_DECLINED => [
                'bg' => 'rgba(239, 68, 68, 0.12)',
                'color' => '#dc2626',
                'border' => 'rgba(239, 68, 68, 0.3)',
                'icon' => 'fa-solid fa-circle-xmark',
            ],
            self::STATUS_CANCELLED => [
                'bg' => 'rgba(148, 163, 184, 0.15)',
                'color' => '#64748b',
                'border' => 'rgba(148, 163, 184, 0.3)',
                'icon' => 'fa-solid fa-ban',
            ],
            default => [
                'bg' => 'rgba(148, 163, 184, 0.1)',
                'color' => '#64748b',
                'border' => 'rgba(148, 163, 184, 0.2)',
                'icon' => 'fa-solid fa-circle-info',
            ],
        };
    }

    /**
     * Get readable event type label.
     */
    public function getEventTypeLabel(?string $locale = null): string
    {
        $loc = $locale ?: app()->getLocale();

        return match ($this->event_type) {
            'birthday' => __('Birthday Party', [], $loc),
            'wedding' => __('Wedding & Engagement', [], $loc),
            'corporate' => __('Corporate Event & Business Dinner', [], $loc),
            'family_celebration' => __('Family Celebration', [], $loc),
            'anniversary' => __('Anniversary', [], $loc),
            'kids_party' => __("Children's Party", [], $loc),
            'other' => __('Other Custom Event', [], $loc),
            default => ucfirst(str_replace('_', ' ', $this->event_type)),
        };
    }

    /**
     * Get readable venue arrangement label.
     */
    public function getVenueArrangementLabel(?string $locale = null): string
    {
        $loc = $locale ?: app()->getLocale();

        return match ($this->venue_arrangement) {
            self::VENUE_INDOOR => __('Indoor Hall', [], $loc),
            self::VENUE_OUTDOOR => __('Outdoor / Terrace', [], $loc),
            self::VENUE_PRIVATE_ROOM => __('Private VIP Room', [], $loc),
            default => __('No Preference', [], $loc),
        };
    }

    /**
     * Format event date according to locale.
     */
    public function getFormattedDate(?string $locale = null): string
    {
        $loc = $locale ?: app()->getLocale();
        try {
            return Carbon::parse($this->event_date)->locale($loc)->isoFormat('dddd, D MMMM YYYY');
        } catch (\Throwable) {
            return $this->event_date;
        }
    }

    /**
     * Format start and end time.
     */
    public function getFormattedTime(): string
    {
        if (filled($this->start_time) && filled($this->end_time)) {
            return $this->start_time.' - '.$this->end_time;
        }

        if (filled($this->start_time)) {
            return $this->start_time;
        }

        return __('Flexible / Not specified');
    }
}
