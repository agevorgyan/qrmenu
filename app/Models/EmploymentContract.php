<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentContract extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'employment_contracts';

    protected $fillable = [
        'vendor_id',
        'user_id',
        'contract_number',
        'contract_type',
        'start_date',
        'end_date',
        'probation_end_date',
        'wage_amount',
        'wage_type',
        'working_hours_per_week',
        'file_path',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'probation_end_date' => 'date',
            'wage_amount' => 'decimal:2',
            'working_hours_per_week' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getBaseSalaryAttribute(): float
    {
        return (float) $this->wage_amount;
    }
}
