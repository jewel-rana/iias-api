<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name',
        'code',
        'is_system',
        'is_active',
        'sort_order',
        'permissions',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function allows(string $permission): bool
    {
        return Permissions::allows($this->permissions, $permission);
    }

    public function isStaff(): bool
    {
        if (in_array($this->code, ['admin', 'collector'], true)) {
            return true;
        }

        return $this->allows(Permissions::ALL)
            || $this->allows(Permissions::MEMBERS_VIEW)
            || $this->allows(Permissions::COLLECTION_VIEW)
            || $this->allows(Permissions::COLLECTION_COLLECT)
            || $this->allows(Permissions::ROLES_MANAGE);
    }

    public static function memberId(): ?int
    {
        return static::query()->where('code', 'member')->value('id');
    }
}
