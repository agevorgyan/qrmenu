<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HaccpChecklist extends Model
{
    use BelongsToVendor, HasFactory;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const TYPE_OPENING = 'opening';

    public const TYPE_CLOSING = 'closing';

    public const TYPE_SANITATION = 'sanitation';

    public const TYPE_HYGIENE = 'hygiene';

    protected $table = 'haccp_checklists';

    protected $fillable = [
        'vendor_id',
        'title',
        'type',
        'shift',
        'completed_by',
        'completed_at',
        'status',
        'notes',
        'items',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'items' => 'array',
    ];

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function passRate(): float
    {
        $items = $this->items ?? [];
        if (empty($items)) {
            return 100.0;
        }

        $passed = collect($items)->filter(fn ($i) => ! empty($i['is_passed']))->count();

        return round(($passed / count($items)) * 100.0, 1);
    }
}
