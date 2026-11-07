<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BankReconciliation extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'bank_reconciliations';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'bank_account_id',
        'reconciliation_number',
        'statement_date',
        'statement_ending_balance',
        'gl_book_balance',
        'difference',
        'status', // draft, completed
        'reconciled_by',
        'reconciled_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_ending_balance' => 'decimal:2',
            'gl_book_balance' => 'decimal:2',
            'difference' => 'decimal:2',
            'reconciled_at' => 'datetime',
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

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}

