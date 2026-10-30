<?php

namespace App\Models;

use App\Models\Traits\BelongsToVendor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosKitchenStation extends Model
{
    use BelongsToVendor, HasFactory;

    protected $table = 'pos_kitchen_stations';

    protected $fillable = [
        'uuid',
        'vendor_id',
        'location_id',
        'name',
        'slug',
        'color',
        'printer_ip',
        'printer_port',
        'printer_type',
        'paper_width',
        'auto_print',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'printer_port' => 'integer',
            'auto_print' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->slug) && ! empty($model->name)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForLocation(Builder $query, ?int $locationId): Builder
    {
        if ($locationId) {
            return $query->where(function ($q) use ($locationId) {
                $q->where('location_id', $locationId)
                    ->orWhereNull('location_id');
            });
        }

        return $query;
    }

    /**
     * Default station definitions if vendor has no custom configured stations.
     */
    public static function getDefaultStations(): array
    {
        return [
            [
                'name' => 'Kitchen',
                'slug' => 'kitchen',
                'color' => '#f59e0b', // Amber
                'printer_type' => 'network',
                'paper_width' => '80mm',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Bar',
                'slug' => 'bar',
                'color' => '#3b82f6', // Blue
                'printer_type' => 'network',
                'paper_width' => '80mm',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Coffee',
                'slug' => 'coffee',
                'color' => '#10b981', // Emerald
                'printer_type' => 'network',
                'paper_width' => '80mm',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Dessert',
                'slug' => 'dessert',
                'color' => '#ec4899', // Pink
                'printer_type' => 'network',
                'paper_width' => '80mm',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];
    }
}
