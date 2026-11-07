<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinancialAuditLog extends Model
{
    use BelongsToVendor, HasFactory;

    public $timestamps = false;

    protected $table = 'financial_audit_logs';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'amount',
        'currency',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Record a new financial audit log entry.
     */
    public static function record(
        Vendor $vendor,
        string $action,
        Model $auditable,
        ?User $user = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?float $amount = null,
        ?string $currency = null,
        ?int $locationId = null
    ): self {
        return self::create([
            'vendor_id' => $vendor->id,
            'location_id' => $locationId ?? ($auditable->location_id ?? null),
            'user_id' => $user?->id ?? auth()->id(),
            'action' => $action,
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'amount' => $amount,
            'currency' => $currency ?? $vendor->currency ?? 'AMD',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
