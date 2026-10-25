<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Database\Factories\AiWaiterSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiWaiterSession extends Model
{
    /** @use HasFactory<AiWaiterSessionFactory> */
    use BelongsToVendor, HasFactory;

    protected $fillable = [
        'vendor_id',
        'location_id',
        'session_token',
        'table_number',
        'entry_context',
        'language',
        'status',
        'conversation_state',
        'preferences',
        'questions_history',
        'answers_history',
        'recommendations',
        'cart_items',
        'order_id',
        'total_order_amount',
        'completed_at',
    ];

    protected $casts = [
        'conversation_state' => 'array',
        'preferences' => 'array',
        'questions_history' => 'array',
        'answers_history' => 'array',
        'recommendations' => 'array',
        'cart_items' => 'array',
        'total_order_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (AiWaiterSession $session) {
            if ($session->location_id && $session->vendor_id) {
                $location = Location::withoutGlobalScopes()->find($session->location_id);
                if ($location && (int) $location->vendor_id !== (int) $session->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor location assigned to AI session.');
                }
            }

            if ($session->order_id && $session->vendor_id) {
                $order = Order::withoutGlobalScopes()->find($session->order_id);
                if ($order && (int) $order->vendor_id !== (int) $session->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor order assigned to AI session.');
                }
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function usageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class, 'session_id');
    }

    public function getRouteKeyName(): string
    {
        return 'session_token';
    }

    /**
     * Resolve model by ID or opaque session_token seamlessly.
     */
    public static function find($id, $columns = ['*'])
    {
        if (is_string($id) && ! ctype_digit($id)) {
            return static::query()->where('session_token', $id)->first($columns);
        }

        return static::query()->find($id, $columns);
    }

    /**
     * Resolve model by ID or opaque session_token seamlessly, or fail.
     */
    public static function findOrFail($id, $columns = ['*'])
    {
        if (is_string($id) && ! ctype_digit($id)) {
            return static::query()->where('session_token', $id)->firstOrFail($columns);
        }

        return static::query()->findOrFail($id, $columns);
    }

    /**
     * Generate a cryptographically random, unguessable session token.
     */
    public static function generateSecureToken(): string
    {
        return 'ais_'.bin2hex(random_bytes(24));
    }
}
