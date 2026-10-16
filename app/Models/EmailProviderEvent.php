<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model representing a verified or received provider webhook event (delivered, bounced, etc.).
 *
 * @property int $id
 * @property int|null $email_log_id
 * @property string $provider
 * @property string $event_type
 * @property string|null $provider_message_id
 * @property string|null $recipient_email
 * @property array|null $payload
 * @property bool $signature_verified
 * @property Carbon|null $occurred_at
 * @property Carbon $created_at
 * @property-read EmailLog|null $log
 */
class EmailProviderEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'email_provider_events';

    protected $fillable = [
        'email_log_id',
        'provider',
        'event_type',
        'provider_message_id',
        'recipient_email',
        'payload',
        'signature_verified',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_verified' => 'boolean',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}
