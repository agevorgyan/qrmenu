<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model representing an individual dispatch attempt of an email log.
 *
 * @property int $id
 * @property int $email_log_id
 * @property int $attempt_number
 * @property string $status
 * @property string|null $connection_name
 * @property int|null $duration_ms
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon $created_at
 * @property-read EmailLog $log
 */
class EmailAttempt extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'email_attempts';

    protected $fillable = [
        'email_log_id',
        'attempt_number',
        'status',
        'connection_name',
        'duration_ms',
        'error_code',
        'error_message',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}
