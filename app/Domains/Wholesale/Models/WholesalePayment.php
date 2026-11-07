<?php

namespace App\Domains\Wholesale\Models;

use App\Models\CashRegister;
use App\Models\JournalEntry;
use App\Models\Traits\BelongsToVendor;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WholesalePayment extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'wholesale_payments';

    protected $fillable = [
        'vendor_id',
        'customer_id',
        'seller_id',
        'payment_number',
        'payment_date',
        'payment_method',
        'amount',
        'allocated_amount',
        'unallocated_amount',
        'status',
        'reference_number',
        'bank_account_id',
        'cash_register_id',
        'journal_entry_id',
        'notes',
        'received_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:4',
            'allocated_amount' => 'decimal:4',
            'unallocated_amount' => 'decimal:4',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WholesaleCustomer::class, 'customer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(WholesaleSeller::class, 'seller_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(WholesaleSellerBankAccount::class, 'bank_account_id');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'cash_register_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(WholesalePaymentAllocation::class, 'payment_id');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
