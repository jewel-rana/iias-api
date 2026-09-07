<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyDue extends Model
{
    protected $fillable = [
        'member_id',
        'billing_month',
        'amount_due',
        'amount_paid',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'billing_month' => 'date',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function remaining(): int
    {
        return max(0, $this->amount_due - $this->amount_paid);
    }

    public function refreshStatus(): void
    {
        if ($this->amount_paid <= 0) {
            $this->status = 'unpaid';
        } elseif ($this->amount_paid < $this->amount_due) {
            $this->status = 'partial';
        } else {
            $this->status = 'paid';
        }
        $this->save();
    }
}
