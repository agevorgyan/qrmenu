<?php

declare(strict_types=1);

namespace App\Domains\Wholesale\Models;

use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ScopedBy([TenantScope::class])]
class WholesaleTaxDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'supply_date' => 'date',
            'parent_document_date' => 'date',
            'is_adjustment' => 'boolean',
            'taxable_amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'seller_snapshot' => 'array',
            'customer_snapshot' => 'array',
            'is_xsd_valid' => 'boolean',
            'xsd_validation_errors' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(WholesaleSeller::class, 'seller_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WholesaleCustomer::class, 'customer_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(WholesaleSale::class, 'sale_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WholesaleOrder::class, 'order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WholesaleTaxDocumentItem::class, 'tax_document_id')->orderBy('line_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isInvoice(): bool
    {
        return $this->document_type === 'TAX_INVOICE';
    }

    public function isAccountingDocument(): bool
    {
        return $this->document_type === 'ACCOUNTING_DOCUMENT';
    }
}
