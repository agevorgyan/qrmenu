<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class HaccpCcp extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    public const TYPE_RECEIVING = 'receiving';

    public const TYPE_STORAGE = 'storage';

    public const TYPE_COOKING = 'cooking';

    public const TYPE_COOLING = 'cooling';

    public const TYPE_HOLDING = 'holding';

    public const TYPE_REHEATING = 'reheating';

    public const TYPE_SANITATION = 'sanitation';

    protected $table = 'haccp_ccps';

    protected $fillable = [
        'vendor_id',
        'name',
        'code',
        'type',
        'target_type',
        'min_value',
        'max_value',
        'unit',
        'critical_limit_description',
        'corrective_action_instructions',
        'frequency',
        'is_active',
    ];

    protected $casts = [
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function temperatureLogs(): HasMany
    {
        return $this->hasMany(HaccpTemperatureLog::class, 'ccp_id');
    }

    public function isWithinLimit(float $value): bool
    {
        if ($this->min_value !== null && $value < (float) $this->min_value) {
            return false;
        }

        if ($this->max_value !== null && $value > (float) $this->max_value) {
            return false;
        }

        return true;
    }

    public function isBreached(float $value): bool
    {
        return ! $this->isWithinLimit($value);
    }
}
