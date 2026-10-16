<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Model representing an auditable outgoing email log record.
 *
 * @property int $id
 * @property string $uuid
 * @property string $recipient_email
 * @property string|null $recipient_name
 * @property string $subject
 * @property string $category
 * @property string|null $mailable_class
 * @property int|null $sender_identity_id
 * @property string $from_email
 * @property string|null $from_name
 * @property string|null $reply_to_email
 * @property int|null $email_connection_id
 * @property string|null $connection_name
 * @property string|null $transport
 * @property string $status
 * @property string|null $provider_message_id
 * @property string|null $queue_job_id
 * @property int $attempts
 * @property int|null $duration_ms
 * @property string|null $related_type
 * @property int|null $related_id
 * @property int|null $vendor_id
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $delivered_at
 * @property Carbon|null $bounced_at
 * @property Carbon|null $last_attempt_at
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read EmailSenderIdentity|null $senderIdentity
 * @property-read EmailConnection|null $connection
 * @property-read Vendor|null $vendor
 * @property-read Collection|EmailAttempt[] $attemptsList
 * @property-read Collection|EmailProviderEvent[] $providerEvents
 */
class EmailLog extends Model
{
    use HasFactory;

    protected $table = 'email_logs';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent'; // Accepted by transport

    public const STATUS_DELIVERED = 'delivered'; // Verified by provider webhook

    public const STATUS_FAILED = 'failed';

    public const STATUS_BOUNCED = 'bounced';

    public const STATUS_COMPLAINED = 'complained';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'recipient_email',
        'recipient_name',
        'subject',
        'category',
        'mailable_class',
        'sender_identity_id',
        'from_email',
        'from_name',
        'reply_to_email',
        'email_connection_id',
        'connection_name',
        'transport',
        'status',
        'provider_message_id',
        'queue_job_id',
        'attempts',
        'duration_ms',
        'related_type',
        'related_id',
        'vendor_id',
        'error_code',
        'error_message',
        'delivered_at',
        'bounced_at',
        'last_attempt_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'duration_ms' => 'integer',
            'delivered_at' => 'datetime',
            'bounced_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EmailLog $log) {
            if (empty($log->uuid)) {
                $log->uuid = (string) Str::uuid();
            }
        });
    }

    public function senderIdentity(): BelongsTo
    {
        return $this->belongsTo(EmailSenderIdentity::class, 'sender_identity_id');
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(EmailConnection::class, 'email_connection_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function attemptsList(): HasMany
    {
        return $this->hasMany(EmailAttempt::class, 'email_log_id')->orderBy('attempt_number', 'asc');
    }

    public function providerEvents(): HasMany
    {
        return $this->hasMany(EmailProviderEvent::class, 'email_log_id')->orderBy('occurred_at', 'desc');
    }

    public function scopeForCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeForStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isSent(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_DELIVERED], true);
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_BOUNCED], true);
    }

    public function canRetry(): bool
    {
        // Safe retry eligibility
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_CANCELLED], true)
            && ! empty($this->mailable_class)
            && class_exists($this->mailable_class);
    }

    public function getStatusBadge(): array
    {
        return match ($this->status) {
            self::STATUS_DELIVERED => ['class' => 'badge-emerald', 'icon' => 'fa-check-double', 'label' => 'Delivered (Verified)'],
            self::STATUS_SENT => ['class' => 'badge-cyan', 'icon' => 'fa-paper-plane', 'label' => 'Accepted by SMTP'],
            self::STATUS_PROCESSING => ['class' => 'badge-amber', 'icon' => 'fa-spinner fa-spin', 'label' => 'Processing'],
            self::STATUS_QUEUED => ['class' => 'badge-indigo', 'icon' => 'fa-clock', 'label' => 'Queued'],
            self::STATUS_FAILED => ['class' => 'badge-rose', 'icon' => 'fa-triangle-exclamation', 'label' => 'Failed'],
            self::STATUS_BOUNCED => ['class' => 'badge-rose', 'icon' => 'fa-arrow-turn-down', 'label' => 'Bounced'],
            self::STATUS_COMPLAINED => ['class' => 'badge-purple', 'icon' => 'fa-flag', 'label' => 'Complaint'],
            self::STATUS_CANCELLED => ['class' => 'badge-amber', 'icon' => 'fa-ban', 'label' => 'Cancelled'],
            default => ['class' => 'badge-cyan', 'icon' => 'fa-envelope', 'label' => ucfirst($this->status)],
        };
    }
}
