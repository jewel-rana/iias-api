<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberJoinRequest extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'email',
        'address',
        'photo_path',
        'preferred_monthly_amount',
        'referral_code',
        'referred_by_member_id',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }
}
