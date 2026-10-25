<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model representing an internal note on an Event Planning Inquiry.
 *
 * @property int $id
 * @property int $vendor_id
 * @property int $event_inquiry_id
 * @property int $user_id
 * @property string $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Vendor|null $vendor
 * @property-read EventInquiry|null $eventInquiry
 * @property-read User|null $user
 */
class EventInquiryNote extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'event_inquiry_notes';

    protected $fillable = [
        'vendor_id',
        'event_inquiry_id',
        'user_id',
        'note',
    ];

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
