<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalaryPayment extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'salary_payments';

    protected $fillable = [
        'vendor_id',
        'payroll_period_id',
        'payroll_item_id',
        'user_id',
        'payment_number',
        'payment_date',
        'amount',
        'payment_method',
        'reference_number',
        'proof_document_path',
        'notes',
        'created_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
