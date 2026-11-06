<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeatureCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_translations',
        'description',
        'description_translations',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'description_translations' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class, 'category_id')->orderBy('sort_order', 'asc');
    }

    public function activeFeatures(): HasMany
    {
        return $this->hasMany(Feature::class, 'category_id')
            ->where('is_active', true)
            ->where('is_archived', false)
            ->orderBy('sort_order', 'asc');
    }

    public function localizedName(?string $locale = null): string
    {
        $loc = strtolower(trim($locale ?: app()->getLocale() ?: 'hy'));
        $translations = $this->name_translations ?? [];

        return $translations[$loc]
            ?? $translations['hy']
            ?? $translations['en']
            ?? $this->name;
    }

    public function localizedDescription(?string $locale = null): ?string
    {
        $loc = strtolower(trim($locale ?: app()->getLocale() ?: 'hy'));
        $translations = $this->description_translations ?? [];

        return $translations[$loc]
            ?? $translations['hy']
            ?? $translations['en']
            ?? $this->description;
    }
}
