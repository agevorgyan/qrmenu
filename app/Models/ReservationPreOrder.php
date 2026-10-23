<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Model representing a pre-order attached to a table reservation.
 *
 * @property int $id
 * @property string $uuid
 * @property int $reservation_id
 * @property int $vendor_id
 * @property string $status
 * @property array|null $items
 * @property float $subtotal
 * @property float $total_amount
 * @property string $currency
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Reservation $reservation
 * @property-read Vendor $vendor
 */
class ReservationPreOrder extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'reservation_pre_orders';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_PREPARING = 'preparing';

    public const STATUS_READY = 'ready';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'uuid',
        'reservation_id',
        'vendor_id',
        'status',
        'items',
        'subtotal',
        'total_amount',
        'currency',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'subtotal' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReservationPreOrder $preOrder) {
            if (empty($preOrder->uuid)) {
                $preOrder->uuid = (string) Str::uuid();
            }
            if (empty($preOrder->vendor_id) && $preOrder->reservation) {
                $preOrder->vendor_id = $preOrder->reservation->vendor_id;
            }
            if (empty($preOrder->currency)) {
                $preOrder->currency = $preOrder->vendor?->currency ?: 'AMD';
            }
        });
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    /**
     * Build formatted summary text of pre-ordered items.
     */
    public function getFormattedSummary(?string $locale = null): string
    {
        $items = $this->items ?? [];
        if (empty($items)) {
            return __('No pre-ordered items', [], $locale ?: 'en');
        }

        $lines = [];
        foreach ($items as $item) {
            $qty = $item['quantity'] ?? 1;
            $name = $item['name'] ?? ($item['product_name'] ?? 'Item');

            // Check if multilingual name exists
            if (! empty($item['name_translations'][$locale])) {
                $name = $item['name_translations'][$locale];
            }

            $variation = ! empty($item['variation_name']) ? " ({$item['variation_name']})" : '';
            $price = isset($item['subtotal']) ? number_format((float) $item['subtotal']).' '.$this->currency : '';

            $lines[] = "{$qty}x {$name}{$variation}".($price ? " — {$price}" : '');
        }

        return implode(', ', $lines);
    }
}
