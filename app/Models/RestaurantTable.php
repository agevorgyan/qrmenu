<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Model representing a physical or logical table in a restaurant.
 *
 * @property int $id
 * @property string $uuid
 * @property int $vendor_id
 * @property int|null $location_id
 * @property string $table_number
 * @property string|null $name
 * @property int $capacity
 * @property int $min_capacity
 * @property string|null $section
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Vendor $vendor
 * @property-read Location|null $location
 * @property-read Collection|Reservation[] $reservations
 */
class RestaurantTable extends Model
{
    use BelongsToVendor, HasFactory, SoftDeletes;

    protected $table = 'restaurant_tables';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'table_number',
        'name',
        'capacity',
        'min_capacity',
        'section',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'min_capacity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (RestaurantTable $table) {
            if (empty($table->uuid)) {
                $table->uuid = (string) Str::uuid();
            }
            if (empty($table->min_capacity)) {
                $table->min_capacity = 1;
            }
            if (empty($table->capacity)) {
                $table->capacity = 4;
            }
        });

        static::saving(function (RestaurantTable $table) {
            if ($table->location_id && $table->vendor_id) {
                $location = Location::withoutGlobalScopes()->find($table->location_id);
                if ($location && (int) $location->vendor_id !== (int) $table->vendor_id) {
                    throw new \InvalidArgumentException('Cross-vendor location assigned to table.');
                }
            }
            if ($table->min_capacity > $table->capacity) {
                $table->min_capacity = $table->capacity;
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'restaurant_table_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForCapacity(Builder $query, int $guestCount): Builder
    {
        return $query->where('capacity', '>=', $guestCount)
            ->where('min_capacity', '<=', $guestCount);
    }

    public function getDisplayNameAttribute(): string
    {
        if (! empty($this->name) && $this->name !== $this->table_number) {
            return "{$this->table_number} ({$this->name})";
        }

        return (string) $this->table_number;
    }
}
