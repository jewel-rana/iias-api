<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'member_code',
        'name',
        'phone',
        'monthly_amount',
        'collector_name',
        'status',
        'total_paid',
        'outstanding',
        'advance',
        'paid_months',
        'due_months',
        'advance_months',
        'referral_code',
        'status_flag',
    ];

    public function dues(): HasMany
    {
        return $this->hasMany(MonthlyDue::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
