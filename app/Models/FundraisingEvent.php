<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundraisingEvent extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'goal_amount',
        'raised_amount',
        'donor_count',
        'cover_path',
        'venue',
        'starts_at',
        'ends_at',
        'status',
        'accept_members',
        'accept_non_members',
        'require_referral_for_guests',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'accept_members' => 'boolean',
            'accept_non_members' => 'boolean',
            'require_referral_for_guests' => 'boolean',
        ];
    }

    public function donations(): HasMany
    {
        return $this->hasMany(EventDonation::class);
    }
}
