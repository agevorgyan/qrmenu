<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'chart_of_accounts';

    protected $fillable = [
        'vendor_id',
        'code',
        'name',
        'type', // asset, liability, equity, revenue, expense
        'subtype', // cash, bank, accounts_receivable, inventory, accounts_payable, etc.
        'currency',
        'current_balance',
        'is_system',
        'is_active',
        'parent_id',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }

    public function cashRegisters(): HasMany
    {
        return $this->hasMany(CashRegister::class, 'account_id');
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class, 'account_id');
    }

    /**
     * Account is normal debit balance (Asset, Expense, or Contra-Revenue).
     */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true) || $this->subtype === 'contra_revenue';
    }

    /**
     * Account is normal credit balance (Liability, Equity, standard Revenue).
     */
    public function isCreditNormal(): bool
    {
        return in_array($this->type, ['liability', 'equity', 'revenue'], true) && $this->subtype !== 'contra_revenue';
    }

    /**
     * Recalculate and update current balance from journal entry lines.
     */
    public function recalculateBalance(): float
    {
        $debits = (float) $this->journalLines()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->where('journal_entry_lines.type', 'debit')
            ->sum('journal_entry_lines.amount_base_currency');

        $credits = (float) $this->journalLines()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereIn('journal_entries.status', ['posted', 'reversed'])
            ->where('journal_entry_lines.type', 'credit')
            ->sum('journal_entry_lines.amount_base_currency');

        $balance = $this->isDebitNormal() ? ($debits - $credits) : ($credits - $debits);
        $this->update(['current_balance' => round($balance, 2)]);

        return (float) $this->current_balance;
    }
}
