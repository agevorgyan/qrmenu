<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use App\Models\Traits\HasContentTranslations;
use App\Services\StorageService;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use BelongsToVendor, HasContentTranslations, HasFactory, SoftDeletes;

    public const DEFAULT_IMAGE = '/images/default-dish.png';

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->price !== null && $product->price < 0) {
                throw new \InvalidArgumentException('Product price cannot be negative.');
            }

            if ($product->category_id && $product->vendor_id) {
                $category = Category::withoutGlobalScopes()->find($product->category_id);
                if ($category && (int) $category->vendor_id !== (int) $product->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor category assignment forbidden.');
                }
            }
        });

        static::deleting(function (Product $product) {
            $product->deleteImageFile();
        });
    }

    /**
     * Delete the product's associated image file from public storage if stored locally.
     */
    public function deleteImageFile(): void
    {
        $raw = $this->getRawOriginal('image');
        if (! empty($raw) && ! str_contains($raw, 'default-dish')) {
            if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
                $parsed = parse_url($raw, PHP_URL_PATH);
                if ($parsed && str_starts_with($parsed, '/storage/')) {
                    $raw = $parsed;
                } else {
                    return;
                }
            }

            if (str_contains($raw, '..')) {
                return;
            }

            try {
                $storageService = app(StorageService::class);
                $cleanPath = $storageService->cleanPath($raw);
                if ($this->vendor && $storageService->isVendorScopedPath($cleanPath, $this->vendor)) {
                    $storageService->delete($cleanPath, $this->vendor);
                } elseif (str_starts_with($cleanPath, 'products/')) {
                    if (Storage::disk('public')->exists($cleanPath)) {
                        Storage::disk('public')->delete($cleanPath);
                    }
                }
            } catch (\Throwable $e) {
                // Silently handle cleanup error
            }
        }
    }

    /**
     * Get the product image URL or the default dish/drink placeholder.
     */
    public function getImageAttribute(?string $value): string
    {
        if (empty($value) || trim($value) === '' || str_contains($value, 'photo-1546069901-ba9599a7e63c') || str_contains($value, 'photo-1544025162-d76694265947')) {
            return self::DEFAULT_IMAGE;
        }

        return $value;
    }

    /**
     * Check if product has a genuine custom image uploaded or specified.
     */
    public function hasCustomImage(): bool
    {
        $raw = $this->getRawOriginal('image');

        return ! empty($raw) && trim($raw) !== '' && ! str_contains($raw, 'default-dish') && ! str_contains($raw, 'photo-1546069901-ba9599a7e63c');
    }

    protected $fillable = [
        'vendor_id',
        'category_id',
        'name',
        'name_translations',
        'description',
        'description_translations',
        'price',
        'discount_price',
        'discount_days',
        'discount_start_time',
        'discount_end_time',
        'is_discount_active',
        'image',
        'gallery',
        'dietary_tags',
        'calories',
        'protein_g',
        'carbs_g',
        'fat_g',
        'preparation_time_min',
        'is_featured',
        'ai_priority',
        'ai_priority_level',
        'ai_group',
        'ai_tags',
        'ai_spicy_level',
        'ai_pairs_with',
        'ai_enabled',
        'is_available',
        'available_start_time',
        'available_end_time',
        'available_days',
        'available_for_dine_in',
        'available_for_takeaway',
        'available_for_delivery',
        'sort_order',
    ];

    protected $casts = [
        'name_translations' => 'array',
        'description_translations' => 'array',
        'gallery' => 'array',
        'dietary_tags' => 'array',
        'discount_days' => 'array',
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'is_discount_active' => 'boolean',
        'protein_g' => 'decimal:1',
        'carbs_g' => 'decimal:1',
        'fat_g' => 'decimal:1',
        'is_featured' => 'boolean',
        'ai_priority' => 'boolean',
        'ai_priority_level' => 'integer',
        'ai_tags' => 'array',
        'ai_spicy_level' => 'integer',
        'ai_pairs_with' => 'array',
        'ai_enabled' => 'boolean',
        'is_available' => 'boolean',
        'available_days' => 'array',
        'available_for_dine_in' => 'boolean',
        'available_for_takeaway' => 'boolean',
        'available_for_delivery' => 'boolean',
    ];

    /**
     * Normalise TIME columns to HH:MM format.
     * PostgreSQL returns HH:MM:SS; MySQL returns HH:MM.
     * These accessors ensure a consistent format across both drivers.
     */
    public function getDiscountStartTimeAttribute(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : $value;
    }

    public function getDiscountEndTimeAttribute(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : $value;
    }

    public function getAvailableStartTimeAttribute(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : $value;
    }

    public function getAvailableEndTimeAttribute(?string $value): ?string
    {
        return $value ? substr($value, 0, 5) : $value;
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function allergens()
    {
        return $this->belongsToMany(Allergen::class, 'product_allergens');
    }

    public function overrides()
    {
        return $this->hasMany(LocationProductOverride::class);
    }

    public function getTranslatedName(?string $lang = null): string
    {
        $lang = $lang ?: app()->getLocale();
        if ($this->name_translations && isset($this->name_translations[$lang]) && ! empty($this->name_translations[$lang])) {
            return $this->name_translations[$lang];
        }

        return $this->name;
    }

    public function getTranslatedDescription(?string $lang = null): string
    {
        $lang = $lang ?: app()->getLocale();
        if ($this->description_translations && isset($this->description_translations[$lang]) && ! empty($this->description_translations[$lang])) {
            return $this->description_translations[$lang];
        }

        return $this->description ?? '';
    }

    /**
     * Check if product discount is currently active based on configured schedule (days & hours).
     */
    public function isDiscountActive(?string $timezone = null): bool
    {
        if ($this->discount_price === null || (float) $this->discount_price <= 0) {
            return false;
        }

        if ((float) $this->discount_price >= (float) $this->price) {
            return false;
        }

        if ($this->is_discount_active === false) {
            return false;
        }

        if ($timezone) {
            $tz = $timezone;
        } else {
            $tenant = app(TenantContext::class)->getTenant();
            if ($tenant && $tenant->id === $this->vendor_id) {
                $tz = $tenant->timezone ?? 'Asia/Yerevan';
            } else {
                $tz = $this->vendor?->timezone ?? 'Asia/Yerevan';
            }
        }

        try {
            $now = Carbon::now($tz);
        } catch (\Exception $e) {
            $tz = 'UTC';
            $now = Carbon::now($tz);
        }

        // 1. Check days of week if specified (e.g. ['mon', 'tue', 'wed', 'thu', 'fri'] or ['monday', ...])
        if (! empty($this->discount_days) && is_array($this->discount_days)) {
            $currentDayShort = strtolower($now->format('D')); // 'mon', 'tue', etc.
            $currentDayFull = strtolower($now->format('l')); // 'monday', 'tuesday', etc.
            $currentDayPrefix = substr($currentDayShort, 0, 3);

            $allowedDays = array_map('strtolower', $this->discount_days);
            $allowedPrefixes = array_map(fn ($d) => substr(strtolower($d), 0, 3), $this->discount_days);

            $dayMatches = in_array($currentDayShort, $allowedDays, true)
                || in_array($currentDayFull, $allowedDays, true)
                || in_array($currentDayPrefix, $allowedPrefixes, true);

            if (! $dayMatches) {
                return false;
            }
        }

        // 2. Check time window if start/end times specified
        if ($this->discount_start_time && $this->discount_end_time) {
            $currentTime = $now->format('H:i:s');
            $startTime = Carbon::parse($this->discount_start_time, $tz)->format('H:i:s');
            $endTime = Carbon::parse($this->discount_end_time, $tz)->format('H:i:s');

            if ($startTime <= $endTime) {
                // Regular daytime window (e.g. 12:00:00 to 16:00:00)
                if ($currentTime < $startTime || $currentTime > $endTime) {
                    return false;
                }
            } else {
                // Overnight window (e.g. 22:00:00 to 02:00:00)
                if ($currentTime < $startTime && $currentTime > $endTime) {
                    return false;
                }
            }
        } elseif ($this->discount_start_time && ! $this->discount_end_time) {
            $currentTime = $now->format('H:i:s');
            $startTime = Carbon::parse($this->discount_start_time, $tz)->format('H:i:s');
            if ($currentTime < $startTime) {
                return false;
            }
        } elseif (! $this->discount_start_time && $this->discount_end_time) {
            $currentTime = $now->format('H:i:s');
            $endTime = Carbon::parse($this->discount_end_time, $tz)->format('H:i:s');
            if ($currentTime > $endTime) {
                return false;
            }
        }

        return true;
    }

    public function getEffectivePrice(?int $locationId = null): float
    {
        $basePrice = (float) $this->price;

        if ($locationId) {
            $override = $this->overrides->firstWhere('location_id', $locationId);
            if ($override && $override->override_price !== null) {
                $basePrice = (float) $override->override_price;
            }
        }

        if ($this->isDiscountActive()) {
            return (float) $this->discount_price;
        }

        return $basePrice;
    }

    public function getRegularPrice(?int $locationId = null): float
    {
        if ($locationId) {
            $override = $this->overrides->firstWhere('location_id', $locationId);
            if ($override && $override->override_price !== null) {
                return (float) $override->override_price;
            }
        }

        return (float) $this->price;
    }

    public function getDiscountPercentage(): ?int
    {
        $regular = $this->getRegularPrice();
        if ($regular <= 0 || ! $this->isDiscountActive()) {
            return null;
        }

        $effective = $this->getEffectivePrice();
        $diff = $regular - $effective;
        if ($diff <= 0) {
            return null;
        }

        return (int) round(($diff / $regular) * 100);
    }

    public function getDiscountScheduleSummary(?string $locale = 'en'): string
    {
        if (! $this->discount_price) {
            return '';
        }

        $dayMap = [
            'en' => ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'],
            'hy' => ['mon' => 'Երկ', 'tue' => 'Երք', 'wed' => 'Չոր', 'thu' => 'Հնգ', 'fri' => 'Ուրբ', 'sat' => 'Շաբ', 'sun' => 'Կիր'],
            'ru' => ['mon' => 'Пн', 'tue' => 'Вт', 'wed' => 'Ср', 'thu' => 'Чт', 'fri' => 'Пт', 'sat' => 'Сб', 'sun' => 'Вс'],
        ];

        $labels = $dayMap[$locale] ?? $dayMap['en'];

        $daysText = '';
        if (empty($this->discount_days) || count($this->discount_days) >= 7) {
            $daysText = $locale === 'hy' ? 'Ամեն օր' : ($locale === 'ru' ? 'Каждый день' : 'Every day');
        } else {
            $prefixes = array_values(array_unique(array_map(fn ($d) => substr(strtolower($d), 0, 3), $this->discount_days)));
            sort($prefixes);

            $weekdays = ['fri', 'mon', 'thu', 'tue', 'wed'];
            $weekends = ['sat', 'sun'];

            if ($prefixes === $weekdays) {
                $daysText = $locale === 'en' ? 'Mon-Fri' : ($locale === 'ru' ? 'Пн-Пт' : 'Երկ-Ուրբ');
            } elseif ($prefixes === $weekends) {
                $daysText = $locale === 'en' ? 'Weekends' : ($locale === 'ru' ? 'Выходные' : 'Հանգստյան օրեր');
            } else {
                $daysText = implode(', ', array_map(fn ($p) => $labels[$p] ?? $p, $prefixes));
            }
        }

        $timeText = '';
        if ($this->discount_start_time && $this->discount_end_time) {
            $s = substr($this->discount_start_time, 0, 5);
            $e = substr($this->discount_end_time, 0, 5);
            $timeText = "{$s} - {$e}";
        } elseif ($this->discount_start_time) {
            $s = substr($this->discount_start_time, 0, 5);
            $timeText = "սկսած {$s}-ից";
        }

        if ($timeText) {
            return "{$daysText}, {$timeText}";
        }

        return $daysText;
    }

    public function isTimeAvailable(?Carbon $now = null): bool
    {
        if (! $this->available_start_time && ! $this->available_end_time && empty($this->available_days)) {
            return true;
        }

        $tz = 'Asia/Yerevan';
        $now = $now ? $now->setTimezone($tz) : Carbon::now($tz);

        // 1. Day of week check
        if (! empty($this->available_days) && is_array($this->available_days) && count($this->available_days) < 7) {
            $dayOfWeek = strtolower($now->format('D'));
            $allowedDays = array_map(fn ($d) => substr(strtolower($d), 0, 3), $this->available_days);
            if (! in_array($dayOfWeek, $allowedDays)) {
                return false;
            }
        }

        // 2. Time window check
        if ($this->available_start_time && $this->available_end_time) {
            $currentTime = $now->format('H:i:s');
            $startTime = Carbon::parse($this->available_start_time, $tz)->format('H:i:s');
            $endTime = Carbon::parse($this->available_end_time, $tz)->format('H:i:s');

            if ($startTime <= $endTime) {
                if ($currentTime < $startTime || $currentTime > $endTime) {
                    return false;
                }
            } else {
                if ($currentTime < $startTime && $currentTime > $endTime) {
                    return false;
                }
            }
        } elseif ($this->available_start_time && ! $this->available_end_time) {
            $currentTime = $now->format('H:i:s');
            $startTime = Carbon::parse($this->available_start_time, $tz)->format('H:i:s');
            if ($currentTime < $startTime) {
                return false;
            }
        } elseif (! $this->available_start_time && $this->available_end_time) {
            $currentTime = $now->format('H:i:s');
            $endTime = Carbon::parse($this->available_end_time, $tz)->format('H:i:s');
            if ($currentTime > $endTime) {
                return false;
            }
        }

        return true;
    }

    public function getAvailabilityScheduleSummary(?string $locale = 'en'): string
    {
        if (! $this->available_start_time && ! $this->available_end_time && empty($this->available_days)) {
            return '';
        }

        $timeText = '';
        if ($this->available_start_time && $this->available_end_time) {
            $s = substr($this->available_start_time, 0, 5);
            $e = substr($this->available_end_time, 0, 5);
            $timeText = "{$s} - {$e}";
        } elseif ($this->available_start_time) {
            $s = substr($this->available_start_time, 0, 5);
            $timeText = $locale === 'hy' ? "սկսած {$s}-ից" : ($locale === 'ru' ? "С {$s}" : "From {$s}");
        } elseif ($this->available_end_time) {
            $e = substr($this->available_end_time, 0, 5);
            $timeText = $locale === 'hy' ? "մինչև {$e}" : ($locale === 'ru' ? "До {$e}" : "Until {$e}");
        }

        $dayMap = [
            'en' => ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'],
            'hy' => ['mon' => 'Երկ', 'tue' => 'Երք', 'wed' => 'Չոր', 'thu' => 'Հնգ', 'fri' => 'Ուրբ', 'sat' => 'Շաբ', 'sun' => 'Կիր'],
            'ru' => ['mon' => 'Пн', 'tue' => 'Вт', 'wed' => 'Ср', 'thu' => 'Чт', 'fri' => 'Пт', 'sat' => 'Сб', 'sun' => 'Вс'],
        ];
        $labels = $dayMap[$locale] ?? $dayMap['en'];

        $daysText = '';
        if (! empty($this->available_days) && count($this->available_days) < 7) {
            $prefixes = array_values(array_unique(array_map(fn ($d) => substr(strtolower($d), 0, 3), $this->available_days)));
            sort($prefixes);

            $weekdays = ['fri', 'mon', 'thu', 'tue', 'wed'];
            $weekends = ['sat', 'sun'];

            if ($prefixes === $weekdays) {
                $daysText = $locale === 'en' ? 'Mon-Fri' : ($locale === 'ru' ? 'Пн-Пт' : 'Երկ-Ուրբ');
            } elseif ($prefixes === $weekends) {
                $daysText = $locale === 'en' ? 'Weekends' : ($locale === 'ru' ? 'Выходные' : 'Հանգստյան օրեր');
            } else {
                $daysText = implode(', ', array_map(fn ($p) => $labels[$p] ?? $p, $prefixes));
            }
        }

        if ($timeText && $daysText) {
            return "{$timeText} ({$daysText})";
        }

        return $timeText ?: $daysText;
    }

    public function isOrderTypeAvailable(?string $type = null): bool
    {
        if (! $type) {
            return true;
        }

        return match ($type) {
            'dine_in' => (bool) ($this->available_for_dine_in ?? true),
            'takeaway' => (bool) ($this->available_for_takeaway ?? true),
            'delivery' => (bool) ($this->available_for_delivery ?? true),
            'whatsapp' => (bool) (($this->available_for_takeaway ?? true) || ($this->available_for_delivery ?? true)),
            default => true,
        };
    }

    public function isAvailableAtLocation(?int $locationId = null): bool
    {
        if (! $this->is_available) {
            return false;
        }
        if ($locationId) {
            $override = $this->overrides->firstWhere('location_id', $locationId);
            if ($override !== null && isset($override->is_available)) {
                return (bool) $override->is_available;
            }
        }

        return true;
    }
}
