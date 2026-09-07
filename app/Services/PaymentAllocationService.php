<?php

namespace App\Services;

use App\Models\Member;
use App\Models\MonthlyDue;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentAllocationService
{
    /**
     * @param  array<int, array{billing_month: string, amount: int}>  $allocations
     */
    public function createPayment(
        Member $member,
        int $amount,
        string $method,
        array $allocations,
        ?int $createdBy = null,
        ?string $idempotencyKey = null,
        ?string $collectorName = null,
    ): Payment {
        $idempotencyKey ??= (string) str()->uuid();

        if ($existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing->load('allocations', 'member');
        }

        $allocationTotal = collect($allocations)->sum('amount');
        if ($allocationTotal !== $amount) {
            throw ValidationException::withMessages([
                'amount' => 'Allocated total must equal payment amount.',
            ]);
        }

        return DB::transaction(function () use ($member, $amount, $method, $allocations, $createdBy, $idempotencyKey, $collectorName) {
            $payment = Payment::query()->create([
                'receipt_number' => 'P-'.(10000 + Payment::query()->count() + 1),
                'member_id' => $member->id,
                'amount' => $amount,
                'payment_method' => $method,
                'collector_name' => $collectorName,
                'payment_date' => now(),
                'status' => 'confirmed',
                'idempotency_key' => $idempotencyKey,
                'created_by' => $createdBy,
            ]);

            foreach ($allocations as $row) {
                $month = Carbon::parse($row['billing_month'])->startOfMonth();
                $due = MonthlyDue::query()->firstOrCreate(
                    [
                        'member_id' => $member->id,
                        'billing_month' => $month->toDateString(),
                    ],
                    [
                        'amount_due' => $member->monthly_amount,
                        'amount_paid' => 0,
                        'status' => 'unpaid',
                    ]
                );

                PaymentAllocation::query()->create([
                    'payment_id' => $payment->id,
                    'member_id' => $member->id,
                    'monthly_due_id' => $due->id,
                    'billing_month' => $month->toDateString(),
                    'amount' => (int) $row['amount'],
                    'status' => 'applied',
                ]);

                $due->amount_paid += (int) $row['amount'];
                $due->refreshStatus();
            }

            $this->recalculateMemberSummary($member->fresh());

            return $payment->load('allocations', 'member');
        });
    }

    public function recalculateMemberSummary(Member $member): void
    {
        $dues = $member->dues()->get();
        $now = now()->startOfMonth();

        $paidMonths = $dues->where('status', 'paid')->count();
        $dueMonths = $dues->filter(function (MonthlyDue $d) use ($now) {
            return $d->billing_month->lte($now) && $d->status !== 'paid';
        })->count();
        $advanceMonths = $dues->filter(function (MonthlyDue $d) use ($now) {
            return $d->billing_month->gt($now) && $d->status === 'paid';
        })->count();

        $outstanding = $dues->filter(fn (MonthlyDue $d) => $d->billing_month->lte($now))
            ->sum(fn (MonthlyDue $d) => $d->remaining());
        $advance = $dues->filter(fn (MonthlyDue $d) => $d->billing_month->gt($now) && $d->status === 'paid')
            ->sum('amount_paid');

        $status = 'paid';
        if ($outstanding > 0) {
            $hasPartial = $dues->contains(fn (MonthlyDue $d) => $d->status === 'partial');
            $status = $hasPartial ? 'partial' : 'unpaid';
            if ($dues->where('status', 'paid')->where(fn ($d) => $d->billing_month->lte($now))->isNotEmpty()
                && $dues->contains(fn (MonthlyDue $d) => $d->billing_month->lte($now) && $d->status !== 'paid')) {
                $status = $hasPartial || $dues->contains(fn (MonthlyDue $d) => $d->status === 'partial')
                    ? 'partial'
                    : 'unpaid';
            }
            if ($dues->contains(fn (MonthlyDue $d) => $d->billing_month->lte($now) && $d->status === 'partial')) {
                $status = 'partial';
            } elseif ($outstanding > 0) {
                $status = 'unpaid';
            }
        }

        $member->update([
            'total_paid' => (int) $dues->sum('amount_paid'),
            'outstanding' => (int) $outstanding,
            'advance' => (int) $advance,
            'paid_months' => $paidMonths,
            'due_months' => $dueMonths,
            'advance_months' => $advanceMonths,
            'status' => $status,
        ]);
    }
}
