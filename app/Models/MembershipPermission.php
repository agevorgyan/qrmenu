<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'membership_id',
        'permission_id',
        'effect',
        'expires_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function membership(): BelongsTo
    {
        return $this->belongsTo(VendorMembership::class, 'membership_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAllow(): bool
    {
        return $this->effect === 'allow';
    }

    public function isDeny(): bool
    {
        return $this->effect === 'deny';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeAllow(Builder $query): Builder
    {
        return $query->where('effect', 'allow');
    }

    public function scopeDeny(Builder $query): Builder
    {
        return $query->where('effect', 'deny');
    }
}
