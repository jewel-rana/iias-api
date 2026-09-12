<?php

namespace App\Models;

use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'password', 'role', 'role_id', 'member_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function accessRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }

    public function hasPermission(string $permission): bool
    {
        $this->loadMissing('accessRole');

        if ($this->role === 'admin' || $this->accessRole?->code === 'admin') {
            return true;
        }

        if ($this->accessRole?->allows($permission)) {
            return true;
        }

        // Legacy collector rows may have the role string but an empty permission set.
        if ($this->role === 'collector' || $this->accessRole?->code === 'collector') {
            return ! in_array($permission, [
                Permissions::ROLES_MANAGE,
                Permissions::ORGANIZATION_MANAGE,
                Permissions::EXPENSE_HEADS_MANAGE,
                Permissions::COMMITTEE_MANAGE,
            ], true);
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        $this->loadMissing('accessRole');

        if ($this->role === 'admin' || $this->accessRole?->code === 'admin') {
            return Permissions::keys();
        }

        $keys = Permissions::expand($this->accessRole?->permissions);
        if ($keys !== []) {
            return $keys;
        }

        if ($this->role === 'collector' || $this->accessRole?->code === 'collector') {
            return Permissions::collector();
        }

        return [];
    }

    public function isStaff(): bool
    {
        if (in_array($this->role, ['admin', 'collector'], true)) {
            return true;
        }

        $this->loadMissing('accessRole');

        return $this->accessRole?->isStaff()
            || $this->hasPermission(Permissions::COLLECTION_COLLECT);
    }

    public function canCollectPayments(): bool
    {
        return $this->hasPermission(Permissions::COLLECTION_COLLECT);
    }

    public static function findByPhone(string $phone): ?self
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($phone === '' && $digits === '') {
            return null;
        }

        return static::query()
            ->where(function ($q) use ($phone, $digits) {
                $q->where('phone', $phone);
                if ($digits !== '') {
                    $q->orWhere('phone', $digits);
                }
            })
            ->first();
    }
}
