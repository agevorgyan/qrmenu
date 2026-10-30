<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PosShift extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_shifts';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'pos_device_id',
        'user_id',
        'closed_by_user_id',
        'status',
        'opened_at',
        'closed_at',
        'opening_float',
        'expected_cash',
        'counted_cash',
        'discrepancy',
        'total_cash_sales',
        'total_card_sales',
        'total_other_sales',
        'total_tips',
        'total_refunds',
        'total_cash_in',
        'total_cash_out',
        'orders_count',
        'opening_notes',
        'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'discrepancy' => 'decimal:2',
            'total_cash_sales' => 'decimal:2',
            'total_card_sales' => 'decimal:2',
            'total_other_sales' => 'decimal:2',
            'total_tips' => 'decimal:2',
            'total_refunds' => 'decimal:2',
            'total_cash_in' => 'decimal:2',
            'total_cash_out' => 'decimal:2',
            'orders_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PosShift $shift): void {
            if (empty($shift->uuid)) {
                $shift->uuid = (string) Str::uuid();
            }
            if (empty($shift->opened_at)) {
                $shift->opened_at = now();
            }
            if (empty($shift->status)) {
                $shift->status = 'open';
            }
            if (! isset($shift->expected_cash)) {
                $shift->expected_cash = $shift->opening_float ?? 0.00;
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

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function device(): BelongsTo
    {
        return $this->posDevice();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(PosCashMovement::class, 'pos_shift_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PosPayment::class, 'pos_shift_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'pos_shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Recalculate expected cash and financial summaries from live payments and movements.
     */
    public function recalculateExpectedCash(): void
    {
        $cashSales = (float) $this->payments()->where('status', 'completed')->where('payment_method', 'cash')->sum('amount');
        $cardSales = (float) $this->payments()->where('status', 'completed')->where('payment_method', 'card')->sum('amount');
        $otherSales = (float) $this->payments()->where('status', 'completed')->whereNotIn('payment_method', ['cash', 'card'])->sum('amount');
        $tips = (float) $this->payments()->where('status', 'completed')->sum('tip_amount');
        $refunds = (float) $this->payments()->whereIn('status', ['refunded', 'voided'])->sum('refunded_amount');

        $cashIn = (float) $this->cashMovements()->where('type', 'cash_in')->sum('amount');
        $cashOut = (float) $this->cashMovements()->whereIn('type', ['cash_out', 'safe_drop', 'expense'])->sum('amount');

        $expectedCash = (float) $this->opening_float + $cashSales + $cashIn - $cashOut;

        $this->update([
            'total_cash_sales' => $cashSales,
            'total_card_sales' => $cardSales,
            'total_other_sales' => $otherSales,
            'total_tips' => $tips,
            'total_refunds' => $refunds,
            'total_cash_in' => $cashIn,
            'total_cash_out' => $cashOut,
            'expected_cash' => max(0, $expectedCash),
            'orders_count' => $this->payments()->distinct('order_id')->count('order_id'),
        ]);
    }

    /**
     * Close the cash shift with physical counted cash and discrepancy calculation.
     */
    public function closeShift(float $countedCash, User $closedBy, ?string $notes = null): void
    {
        $this->recalculateExpectedCash();

        $discrepancy = $countedCash - (float) $this->expected_cash;

        $this->update([
            'status' => 'closed',
            'closed_at' => Carbon::now(),
            'closed_by_user_id' => $closedBy->id,
            'counted_cash' => $countedCash,
            'discrepancy' => $discrepancy,
            'closing_notes' => $notes,
        ]);
    }

    /**
     * Generate X-Report or Z-Report summary payload.
     */
    public function generateReportPayload(): array
    {
        return [
            'shift_id' => $this->id,
            'uuid' => $this->uuid,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'duration_minutes' => $this->opened_at ? (int) $this->opened_at->diffInMinutes($this->closed_at ?? now()) : 0,
            'cashier' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],
            'closed_by' => $this->closedByUser ? [
                'id' => $this->closedByUser->id,
                'name' => $this->closedByUser->name,
            ] : null,
            'opening_float' => (float) $this->opening_float,
            'total_cash_sales' => (float) $this->total_cash_sales,
            'total_card_sales' => (float) $this->total_card_sales,
            'total_other_sales' => (float) $this->total_other_sales,
            'gross_sales' => (float) ($this->total_cash_sales + $this->total_card_sales + $this->total_other_sales),
            'total_tips' => (float) $this->total_tips,
            'total_refunds' => (float) $this->total_refunds,
            'net_sales' => (float) ($this->total_cash_sales + $this->total_card_sales + $this->total_other_sales - $this->total_refunds),
            'cash_in' => (float) $this->total_cash_in,
            'cash_out' => (float) $this->total_cash_out,
            'expected_cash' => (float) $this->expected_cash,
            'counted_cash' => $this->counted_cash !== null ? (float) $this->counted_cash : null,
            'discrepancy' => (float) $this->discrepancy,
            'orders_count' => (int) $this->orders_count,
            'movements_count' => $this->cashMovements()->count(),
            'opening_notes' => $this->opening_notes,
            'closing_notes' => $this->closing_notes,
        ];
    }
}
