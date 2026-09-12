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
        if ($this->role === 'admin') {
            return true;
        }

        $this->loadMissing('accessRole');

        return $this->accessRole?->allows($permission) ?? false;
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        if ($this->role === 'admin') {
            return Permissions::keys();
        }

        $this->loadMissing('accessRole');

        return Permissions::expand($this->accessRole?->permissions);
    }

    public function isStaff(): bool
    {
        if (in_array($this->role, ['admin', 'collector'], true)) {
            return true;
        }

        $this->loadMissing('accessRole');

        return $this->accessRole?->isStaff() ?? false;
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
