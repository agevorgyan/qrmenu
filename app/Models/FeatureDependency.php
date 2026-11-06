<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureDependency extends Model
{
    use HasFactory;

    protected $fillable = [
        'feature_id',
        'depends_on_feature_id',
        'feature_code',
        'depends_on_code',
        'required_value',
        'is_active',
    ];

    protected $casts = [
        'required_value' => 'array',
        'is_active' => 'boolean',
    ];

    public function feature(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'feature_id');
    }

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(Feature::class, 'depends_on_feature_id');
    }
}
