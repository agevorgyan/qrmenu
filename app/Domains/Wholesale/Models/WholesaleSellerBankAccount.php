<?php

namespace App\Domains\Wholesale\Models;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleSellerBankAccount extends Model
{
    use HasFactory;

    protected $table = 'wholesale_seller_bank_accounts';

    protected $fillable = [
        'seller_id',
        'bank_name',
        'account_number',
        'iban',
        'currency',
        'bank_account_id',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(WholesaleSeller::class, 'seller_id');
    }

    public function coreBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function getFormattedAccountAttribute(): string
    {
        return "{$this->bank_name} - {$this->account_number} ({$this->currency})";
    }
}
