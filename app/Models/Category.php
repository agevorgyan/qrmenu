<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Traits\HasContentTranslations;
use App\Services\TenantCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use BelongsToVendor, HasContentTranslations, HasFactory, SoftDeletes;

    protected $fillable = [
        'vendor_id',
        'location_id',
        'name',
        'name_translations',
        'description',
        'image',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category) {
            if ($category->location_id && $category->vendor_id) {
                $location = Location::withoutGlobalScopes()->find($category->location_id);
                if ($location && (int) $location->vendor_id !== (int) $category->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor location assigned to category.');
                }
            }
        });

        static::saved(function (Category $category) {
            if ($category->vendor_id) {
                TenantCache::invalidateMenu((int) $category->vendor_id);
            }
        });

        static::deleted(function (Category $category) {
            if ($category->vendor_id) {
                TenantCache::invalidateMenu((int) $category->vendor_id);
            }
        });
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class)->orderBy('sort_order', 'asc');
    }

    public function getTranslatedName(?string $lang = null): string
    {
        $lang = $lang ?: app()->getLocale();
        if ($this->name_translations && isset($this->name_translations[$lang]) && ! empty($this->name_translations[$lang])) {
            return $this->name_translations[$lang];
        }

        return $this->name;
    }
}
