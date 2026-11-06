<?php

namespace App\Domains\Loyalty\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletUpdateJob extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'wallet_update_jobs';

    protected $fillable = [
        'vendor_id',
        'pass_id',
        'provider',
        'status',
        'attempts',
        'last_error',
        'scheduled_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'scheduled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function pass(): BelongsTo
    {
        return $this->belongsTo(WalletPass::class, 'pass_id');
    }
}
