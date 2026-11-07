<?php

namespace App\Domains\Wholesale\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WholesaleAtgaaClassification extends Model
{
    use HasFactory;

    protected $table = 'wholesale_atgaa_classifications';

    protected $fillable = [
        'code',
        'name_hy',
        'name_en',
        'name_ru',
        'section',
        'is_common',
    ];

    protected function casts(): array
    {
        return [
            'is_common' => 'boolean',
        ];
    }

    /**
     * Get localized display description with code.
     */
    public function getDisplayNameAttribute(): string
    {
        $locale = app()->getLocale();
        $name = match ($locale) {
            'en' => $this->name_en ?: $this->name_hy,
            'ru' => $this->name_ru ?: $this->name_hy,
            default => $this->name_hy,
        };

        return "{$this->code} — {$name}";
    }
}
