<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Meeting extends Model
{
    protected $fillable = [
        'title',
        'purpose',
        'starts_at',
        'location',
        'status',
        'summary',
        'created_by',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'meeting_attendees')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
