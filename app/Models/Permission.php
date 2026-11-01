<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'module',
        'action',
        'description',
        'risk_level',
        'is_sensitive',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_sensitive' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'permission_id',
            'role_id'
        )->withTimestamps();
    }

    public function membershipPermissions(): HasMany
    {
        return $this->hasMany(MembershipPermission::class, 'permission_id');
    }

    public function scopeModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    public function scopeSensitive(Builder $query): Builder
    {
        return $query->where('is_sensitive', true);
    }
}
