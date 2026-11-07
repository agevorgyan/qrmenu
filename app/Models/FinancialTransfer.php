<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FinancialTransfer extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'financial_transfers';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'transfer_number',
        'from_type', // cash_register, bank_account, safe
        'from_id',
        'to_type', // cash_register, bank_account, safe
        'to_id',
        'amount',
        'currency',
        'transfer_date',
        'status', // pending, completed, cancelled
        'initiated_by',
        'approved_by',
        'notes',
        'journal_entry_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transfer_date' => 'date',
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

    public function initiatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
