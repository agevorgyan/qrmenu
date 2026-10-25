<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model representing an activity log entry on an Event Planning Inquiry.
 *
 * @property int $id
 * @property int $vendor_id
 * @property int $event_inquiry_id
 * @property int|null $user_id
 * @property string $action
 * @property string $description
 * @property array|null $metadata
 * @property Carbon $created_at
 * @property-read Vendor|null $vendor
 * @property-read EventInquiry|null $eventInquiry
 * @property-read User|null $user
 */
class EventInquiryActivity extends Model
{
    use BelongsToVendor, HasFactory;

    public $timestamps = false;

    protected $table = 'event_inquiry_activities';

    protected $fillable = [
        'vendor_id',
        'event_inquiry_id',
        'user_id',
        'action',
        'description',
        'metadata',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function eventInquiry(): BelongsTo
    {
        return $this->belongsTo(EventInquiry::class, 'event_inquiry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
