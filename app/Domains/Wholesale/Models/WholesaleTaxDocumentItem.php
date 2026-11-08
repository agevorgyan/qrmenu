<?php

declare(strict_types=1);

namespace App\Domains\Wholesale\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleTaxDocumentItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:5',
            'taxable_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'vat_rate' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function taxDocument(): BelongsTo
    {
        return $this->belongsTo(WholesaleTaxDocument::class, 'tax_document_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WholesaleProduct::class, 'product_id');
    }
}
