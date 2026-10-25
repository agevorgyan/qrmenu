<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ServiceRating extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'service_ratings';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'order_id',
        'table_number',
        'waiter_id',
        'rating',
        'categories',
        'comment',
        'is_anonymous',
        'customer_name',
        'customer_phone',
        'ip_address',
        'user_agent',
        'status',
        'moderation_note',
        'moderated_by',
        'moderated_at',
        'idempotency_key',
    ];

    protected $casts = [
        'rating' => 'integer',
        'categories' => 'array',
        'is_anonymous' => 'boolean',
        'moderated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ServiceRating $rating) {
            if (empty($rating->uuid)) {
                $rating->uuid = (string) Str::uuid();
            }
            if (empty($rating->status)) {
                $rating->status = 'published';
            }
            // Sanitize comment to prevent stored XSS attacks
            if (! empty($rating->comment)) {
                $rating->comment = htmlspecialchars(strip_tags(trim($rating->comment)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }
        });

        static::saving(function (ServiceRating $rating) {
            if ($rating->rating !== null && ($rating->rating < 1 || $rating->rating > 10)) {
                throw new \InvalidArgumentException('Rating score out of bounds.');
            }

            if ($rating->order_id && $rating->vendor_id) {
                $order = Order::withoutGlobalScopes()->find($rating->order_id);
                if ($order && (int) $order->vendor_id !== (int) $rating->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor order attached to service rating.');
                }
            }

            if ($rating->waiter_id && $rating->vendor_id) {
                $waiter = User::find($rating->waiter_id);
                if ($waiter && (int) $waiter->vendor_id !== (int) $rating->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor waiter attached to service rating.');
                }
            }

            if ($rating->location_id && $rating->vendor_id) {
                $location = Location::withoutGlobalScopes()->find($rating->location_id);
                if ($location && (int) $location->vendor_id !== (int) $rating->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor location attached to service rating.');
                }
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeHidden(Builder $query): Builder
    {
        return $query->where('status', 'hidden');
    }

    public function scopeFlagged(Builder $query): Builder
    {
        return $query->where('status', 'flagged');
    }

    public function scopeForWaiter(Builder $query, int $waiterId): Builder
    {
        return $query->where('waiter_id', $waiterId);
    }

    /**
     * Get displayable customer name (respecting anonymity).
     */
    public function getCustomerDisplayName(): string
    {
        if ($this->is_anonymous || empty($this->customer_name)) {
            return __('Anonymous Guest');
        }

        return $this->customer_name;
    }

    public function setModerationStatus(string $status, ?string $note, User $moderator): void
    {
        $this->update([
            'status' => $status,
            'moderation_note' => $note,
            'moderated_by' => $moderator->id,
            'moderated_at' => now(),
        ]);
    }
}
