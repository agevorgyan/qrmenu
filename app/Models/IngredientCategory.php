<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Traits\HasContentTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class IngredientCategory extends Model
{
    use BelongsToVendor, HasContentTranslations, HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'parent_id',
        'name',
        'name_translations',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(IngredientCategory::class, 'parent_id')->orderBy('sort_order');
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'category_id');
    }
}
