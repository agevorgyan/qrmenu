<?php

namespace App\Domains\Wholesale\Models;

use App\Domains\Wholesale\Enums\SellerTaxRegime;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesaleSellerTaxProfile extends Model
{
    use HasFactory;

    protected $table = 'wholesale_seller_tax_profiles';

    protected $fillable = [
        'seller_id',
        'tax_regime',
        'is_vat_payer',
        'vat_rate',
        'effective_from',
        'effective_to',
        'document_series',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'tax_regime' => SellerTaxRegime::class,
            'is_vat_payer' => 'boolean',
            'vat_rate' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'settings' => 'array',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(WholesaleSeller::class, 'seller_id');
    }
}
