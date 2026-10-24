<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionLifecycleEvent extends Model
{
    use BelongsToVendor, HasFactory;

    public $timestamps = false;

    protected $table = 'subscription_lifecycle_events';

    protected $fillable = [
        'vendor_id',
        'subscription_id',
        'actor_id',
        'actor_type',
        'event',
        'from_status',
        'to_status',
        'from_plan_id',
        'to_plan_id',
        'reason',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (SubscriptionLifecycleEvent $event): void {
            if (empty($event->created_at)) {
                $event->created_at = now();
            }

            if ($event->subscription_id && $event->vendor_id) {
                $subscription = Subscription::withoutTenantQuery()->find($event->subscription_id);
                if ($subscription && (int) $subscription->vendor_id !== (int) $event->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor subscription lifecycle event injection detected.');
                }
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'to_plan_id');
    }
}
