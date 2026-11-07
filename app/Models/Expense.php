<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Expense extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'expenses';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'category_id',
        'account_id',
        'supplier_id',
        'expense_number',
        'title',
        'description',
        'amount',
        'currency',
        'tax_amount',
        'expense_date',
        'due_date',
        'status', // draft, pending_approval, approved, rejected, paid, partially_paid, cancelled
        'payment_method', // cash, bank_transfer, card, other
        'cash_register_id',
        'bank_account_id',
        'paid_amount',
        'remaining_amount',
        'paid_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'attachment_path',
        'is_recurring',
        'recurring_frequency',
        'idempotency_key',
        'created_by',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'expense_date' => 'date',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'is_recurring' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (! isset($model->remaining_amount)) {
                $model->remaining_amount = (float) $model->amount - (float) ($model->paid_amount ?? 0);
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinancialCategory::class, 'category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->amount;
    }

    public function setTotalAmountAttribute(mixed $value): void
    {
        $this->attributes['amount'] = $value;
    }

    public function getFinancialCategoryIdAttribute(): ?int
    {
        return $this->category_id;
    }

    public function setFinancialCategoryIdAttribute(mixed $value): void
    {
        $this->attributes['category_id'] = $value;
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function getPaymentStatusAttribute(): string
    {
        $paid = (float) $this->paid_amount;
        $total = (float) $this->amount;

        if ($paid >= $total && $total > 0) {
            return 'paid';
        }
        if ($paid > 0) {
            return 'partially_paid';
        }

        return 'unpaid';
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class, 'expense_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 'partially_paid', 'paid'], true);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function recalculatePaymentStatus(): void
    {
        $paid = (float) $this->payments()->sum('amount');
        $total = (float) $this->amount;
        $remaining = max(0, $total - $paid);

        $this->paid_amount = $paid;
        $this->remaining_amount = $remaining;

        if ($paid >= $total && $total > 0) {
            $this->status = 'paid';
            $this->paid_at = now();
        } elseif ($paid > 0) {
            $this->status = 'partially_paid';
        }

        $this->save();
    }
}
