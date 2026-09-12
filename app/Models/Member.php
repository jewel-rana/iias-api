<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'member_code',
        'name',
        'phone',
        'email',
        'monthly_amount',
        'collector_name',
        'joined_at',
        'status',
        'total_paid',
        'outstanding',
        'advance',
        'paid_months',
        'due_months',
        'advance_months',
        'referral_code',
        'status_flag',
        'role_id',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
        ];
    }

    public function dues(): HasMany
    {
        return $this->hasMany(MonthlyDue::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(EventDonation::class);
    }

    public function accessRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
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
