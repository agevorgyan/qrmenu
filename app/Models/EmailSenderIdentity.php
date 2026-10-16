<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model representing a verified sender identity (From & Reply-To).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $description
 * @property string|null $reply_to_email
 * @property string|null $reply_to_name
 * @property bool $is_active
 * @property bool $is_default
 * @property int|null $email_connection_id
 * @property array|null $assigned_categories
 * @property string $verification_status
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read EmailConnection|null $connection
 * @property-read Collection|EmailRoutingRule[] $routingRules
 * @property-read Collection|EmailLog[] $logs
 */
class EmailSenderIdentity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'email_sender_identities';

    protected $fillable = [
        'name',
        'email',
        'description',
        'reply_to_email',
        'reply_to_name',
        'is_active',
        'is_default',
        'email_connection_id',
        'assigned_categories',
        'verification_status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'assigned_categories' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(EmailConnection::class, 'email_connection_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function routingRules(): HasMany
    {
        return $this->hasMany(EmailRoutingRule::class, 'sender_identity_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(EmailLog::class, 'sender_identity_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Determine whether this sender identity can be safely deleted or deactivated.
     *
     * @return array{can: bool, reason: ?string}
     */
    public function canBeDeleted(): array
    {
        if ($this->is_default) {
            return [
                'can' => false,
                'reason' => 'Cannot delete the active default sender identity. Set another sender as default first.',
            ];
        }

        $activeRulesCount = $this->routingRules()->where('is_active', true)->count();
        if ($activeRulesCount > 0) {
            return [
                'can' => false,
                'reason' => "Cannot delete sender identity because it is assigned to {$activeRulesCount} active routing rule(s). Reassign or disable those rules first.",
            ];
        }

        return [
            'can' => true,
            'reason' => null,
        ];
    }
}
