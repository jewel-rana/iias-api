<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'receipt_number',
        'member_id',
        'amount',
        'payment_method',
        'collector_name',
        'organization_wallet_label',
        'organization_wallet_number',
        'wallet_account',
        'transaction_reference',
        'payment_date',
        'status',
        'idempotency_key',
        'created_by',
        'submitted_by_role',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }
}
