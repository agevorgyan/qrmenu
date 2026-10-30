<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosPrintJob extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_print_jobs';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'order_id',
        'pos_device_id',
        'station_slug',
        'ticket_type',
        'title',
        'content_escpos',
        'content_text',
        'status',
        'attempts',
        'error_message',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'printed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function posDevice(): BelongsTo
    {
        return $this->belongsTo(PosDevice::class, 'pos_device_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function markPrinted(): self
    {
        $this->update([
            'status' => 'printed',
            'printed_at' => Carbon::now(),
            'error_message' => null,
        ]);

        return $this;
    }

    public function markFailed(string $error): self
    {
        $this->increment('attempts');
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);

        return $this;
    }
}
