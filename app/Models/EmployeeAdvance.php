<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAdvance extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'employee_advances';

    protected $fillable = [
        'vendor_id',
        'user_id',
        'amount',
        'requested_date',
        'disbursement_date',
        'payment_method',
        'recovered_amount',
        'remaining_amount',
        'status',
        'reason',
        'approved_by',
        'payroll_period_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recovered_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'requested_date' => 'date',
            'disbursement_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payrollPeriod(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
