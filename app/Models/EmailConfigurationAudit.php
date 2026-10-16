<?php

namespace App\Models;

use App\Services\AuditSanitizer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model representing audit records of email configuration changes.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string $target_type
 * @property int|null $target_id
 * @property string|null $target_name
 * @property array|null $changes
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 * @property-read User|null $user
 */
class EmailConfigurationAudit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'email_configuration_audits';

    protected $fillable = [
        'user_id',
        'action',
        'target_type',
        'target_id',
        'target_name',
        'changes',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Automatic scrubber to ensure secrets are never stored in audit logs.
     */
    public function setChangesAttribute(mixed $value): void
    {
        if (is_array($value)) {
            $value = app(AuditSanitizer::class)->sanitize($value);
            $this->attributes['changes'] = json_encode($value);
        } else {
            $this->attributes['changes'] = $value ? json_encode($value) : null;
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Helper to create an audit record quickly.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public static function log(string $action, string $targetType, ?int $targetId, ?string $targetName, ?array $changes = null): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
