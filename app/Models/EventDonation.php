<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventDonation extends Model
{
    protected $fillable = [
        'receipt_number',
        'fundraising_event_id',
        'donor_type',
        'member_id',
        'donor_name',
        'donor_phone',
        'donor_email',
        'referred_by_member_id',
        'amount',
        'payment_method',
        'collector_name',
        'transaction_reference',
        'payment_date',
        'status',
        'idempotency_key',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(FundraisingEvent::class, 'fundraising_event_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'referred_by_member_id');
    }
}
