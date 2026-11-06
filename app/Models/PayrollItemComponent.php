<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItemComponent extends Model
{
    use HasFactory;

    protected $table = 'payroll_item_components';

    protected $fillable = [
        'payroll_item_id',
        'type',
        'code',
        'name',
        'rate',
        'amount',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'meta' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PayrollItem::class, 'payroll_item_id');
    }
}
