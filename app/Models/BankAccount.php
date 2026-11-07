<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BankAccount extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'bank_accounts';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'account_name',
        'bank_name',
        'account_number',
        'iban',
        'swift',
        'currency',
        'opening_balance',
        'current_balance',
        'account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'is_active' => 'boolean',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class, 'bank_account_id');
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class, 'bank_account_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'bank_account_id');
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class, 'bank_account_id');
    }
}
