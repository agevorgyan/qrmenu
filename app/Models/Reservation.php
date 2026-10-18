<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Model representing a restaurant table reservation.
 *
 * @property int $id
 * @property string $uuid
 * @property string $reference
 * @property int $vendor_id
 * @property int|null $location_id
 * @property string $customer_name
 * @property string $customer_email
 * @property string|null $customer_phone
 * @property string $locale
 * @property int $guest_count
 * @property string|null $table_number
 * @property string|null $table_id
 * @property string $reservation_date
 * @property string $reservation_time
 * @property Carbon|null $reservation_datetime
 * @property string $status
 * @property string|null $notes
 * @property string|null $special_requests
 * @property string|null $cancellation_reason
 * @property string|null $cancellation_policy
 * @property array|null $reminders_sent
 * @property array|null $staff_reminders_sent
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Vendor|null $vendor
 * @property-read Location|null $location
 * @property-read ReservationPreOrder|null $preOrder
 */
class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reservations';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_MODIFIED = 'modified';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'uuid',
        'reference',
        'vendor_id',
        'location_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'locale',
        'guest_count',
        'table_number',
        'table_name',
        'table_id',
        'reservation_date',
        'reservation_time',
        'reservation_datetime',
        'status',
        'notes',
        'special_requests',
        'cancellation_reason',
        'cancellation_policy',
        'reminders_sent',
        'staff_reminders_sent',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'reservation_datetime' => 'datetime',
            'reminders_sent' => 'array',
            'staff_reminders_sent' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            if (empty($reservation->uuid)) {
                $reservation->uuid = (string) Str::uuid();
            }

            if (empty($reservation->reference)) {
                $reservation->reference = 'RES-'.strtoupper(Str::random(6));
            }

            if (empty($reservation->locale)) {
                $reservation->locale = session('app_locale') ?: (app()->getLocale() ?: 'hy');
            }

            if (empty($reservation->reminders_sent)) {
                $reservation->reminders_sent = [];
            }

            if (empty($reservation->staff_reminders_sent)) {
                $reservation->staff_reminders_sent = [];
            }

            $reservation->syncCalculatedDatetime();
        });

        static::updating(function (Reservation $reservation) {
            if ($reservation->isDirty(['reservation_date', 'reservation_time'])) {
                $reservation->syncCalculatedDatetime();
                $reservation->resetFutureReminders();
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function preOrder(): HasOne
    {
        return $this->hasOne(ReservationPreOrder::class, 'reservation_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('reservation_datetime', '>', now())
            ->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_REJECTED, self::STATUS_COMPLETED]);
    }

    /**
     * Compute timestamp using restaurant timezone.
     */
    public function syncCalculatedDatetime(): void
    {
        if (! empty($this->reservation_date) && ! empty($this->reservation_time)) {
            $tz = $this->vendor?->timezone ?: 'Asia/Yerevan';
            $time = trim((string) $this->reservation_time);
            try {
                $localDateTime = Carbon::parse("{$this->reservation_date} {$time}", $tz);
                $this->reservation_datetime = $localDateTime->utc();
            } catch (\Throwable $e) {
                $this->reservation_datetime = Carbon::parse("{$this->reservation_date} {$time}");
            }
        }
    }

    /**
     * Calculate remaining minutes until arrival from now.
     */
    public function minutesUntilArrival(?Carbon $now = null): int
    {
        $now = $now ?: Carbon::now('UTC');
        $arrival = $this->reservation_datetime ? $this->reservation_datetime->copy()->utc() : null;

        if (! $arrival) {
            return -1;
        }

        return (int) $now->diffInMinutes($arrival, false);
    }

    /**
     * Check if the reservation date/time has already passed.
     */
    public function isPassed(?Carbon $now = null): bool
    {
        $now = $now ?: Carbon::now('UTC');

        return $this->minutesUntilArrival($now) <= 0;
    }

    /**
     * Determine if a specific reminder interval has already been sent.
     */
    public function hasReminderBeenSent(int $interval, string $type = 'customer'): bool
    {
        $sent = $type === 'restaurant'
            ? ($this->staff_reminders_sent ?? [])
            : ($this->reminders_sent ?? []);

        return in_array($interval, $sent, true);
    }

    /**
     * Mark a reminder interval as sent.
     */
    public function markReminderSent(int $interval, string $type = 'customer'): void
    {
        if ($type === 'restaurant') {
            $sent = $this->staff_reminders_sent ?? [];
            if (! in_array($interval, $sent, true)) {
                $sent[] = $interval;
                $this->staff_reminders_sent = $sent;
                $this->saveQuietly();
            }
        } else {
            $sent = $this->reminders_sent ?? [];
            if (! in_array($interval, $sent, true)) {
                $sent[] = $interval;
                $this->reminders_sent = $sent;
                $this->saveQuietly();
            }
        }
    }

    /**
     * Reset reminders after a reservation time modification.
     */
    public function resetFutureReminders(): void
    {
        // When rescheduled into the future, clear reminder tracking so fresh reminders can dispatch
        $this->reminders_sent = [];
        $this->staff_reminders_sent = [];
    }

    /**
     * Get formatted date string in localized display.
     */
    public function getFormattedDate(?string $locale = null): string
    {
        $tz = $this->vendor?->timezone ?: 'Asia/Yerevan';
        $dt = $this->reservation_datetime
            ? $this->reservation_datetime->copy()->setTimezone($tz)
            : Carbon::parse($this->reservation_date, $tz);

        return $dt->format('Y-m-d');
    }

    /**
     * Get formatted time string in local display.
     */
    public function getFormattedTime(): string
    {
        $tz = $this->vendor?->timezone ?: 'Asia/Yerevan';
        if ($this->reservation_datetime) {
            return $this->reservation_datetime->copy()->setTimezone($tz)->format('H:i');
        }

        return substr($this->reservation_time, 0, 5);
    }

    /**
     * Get public secure details URL for the customer.
     */
    public function getPublicDetailsUrl(): string
    {
        $vendorSlug = $this->vendor?->slug ?: 'restaurant';

        return url("/m/{$vendorSlug}/reservation/{$this->reference}?token={$this->uuid}");
    }

    /**
     * Check if cancellation is permitted for this reservation.
     */
    public function canCancel(): bool
    {
        return ! in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_REJECTED, self::STATUS_COMPLETED], true)
            && ! $this->isPassed();
    }

    public function getTableNameAttribute(): ?string
    {
        return $this->attributes['table_number'] ?? null;
    }

    public function setTableNameAttribute(?string $value): void
    {
        $this->attributes['table_number'] = $value;
    }
}
