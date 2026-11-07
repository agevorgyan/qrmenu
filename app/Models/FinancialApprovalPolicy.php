<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancialApprovalPolicy extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'financial_approval_policies';

    protected $fillable = [
        'vendor_id',
        'entity_type', // expense, refund, void, transfer, shift_discrepancy
        'threshold_amount',
        'required_role', // manager, vendor_owner, accountant
        'auto_approve_below',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold_amount' => 'decimal:2',
            'auto_approve_below' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
