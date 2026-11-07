<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Income extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'incomes';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'category_id',
        'account_id',
        'income_number',
        'title',
        'description',
        'amount',
        'currency',
        'income_date',
        'status', // draft, approved, received, cancelled
        'payment_method', // cash, bank_transfer, card, other
        'cash_register_id',
        'bank_account_id',
        'received_at',
        'created_by',
        'journal_entry_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'income_date' => 'date',
            'received_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
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

    public function getFinancialCategoryIdAttribute(): ?int
    {
        return $this->category_id;
    }

    public function setFinancialCategoryIdAttribute(mixed $value): void
    {
        $this->attributes['category_id'] = $value;
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
