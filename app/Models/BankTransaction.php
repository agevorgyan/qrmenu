<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BankTransaction extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'bank_transactions';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'bank_account_id',
        'transaction_date',
        'value_date',
        'type', // credit, debit
        'amount',
        'currency',
        'reference_number',
        'counterparty_name',
        'counterparty_account',
        'description',
        'reconciliation_status', // unreconciled, matched, manually_reconciled, excluded
        'matched_source_type',
        'matched_source_id',
        'matched_at',
        'matched_by',
        'journal_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'value_date' => 'date',
            'matched_at' => 'datetime',
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

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matchedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function isMatched(): bool
    {
        return in_array($this->reconciliation_status, ['matched', 'manually_reconciled'], true);
    }
}

