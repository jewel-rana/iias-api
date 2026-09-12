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
    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

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
        ?string $walletAccount = null,
        ?string $transactionReference = null,
        bool $requiresApproval = false,
        ?string $submittedByRole = null,
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

        return DB::transaction(function () use (
            $member,
            $amount,
            $method,
            $allocations,
            $createdBy,
            $idempotencyKey,
            $collectorName,
            $walletAccount,
            $transactionReference,
            $requiresApproval,
            $submittedByRole,
        ) {
            $status = $requiresApproval ? self::STATUS_PENDING : self::STATUS_CONFIRMED;
            $allocationStatus = $requiresApproval ? 'pending' : 'applied';

            $payment = Payment::query()->create([
                'receipt_number' => 'P-'.(10000 + Payment::query()->count() + 1),
                'member_id' => $member->id,
                'amount' => $amount,
                'payment_method' => $method,
                'collector_name' => $collectorName,
                'wallet_account' => $walletAccount,
                'transaction_reference' => $transactionReference,
                'payment_date' => now(),
                'status' => $status,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $createdBy,
                'submitted_by_role' => $submittedByRole,
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
                    'status' => $allocationStatus,
                ]);

                if (! $requiresApproval) {
                    $due->amount_paid += (int) $row['amount'];
                    $due->refreshStatus();
                }
            }

            if (! $requiresApproval) {
                $this->recalculateMemberSummary($member->fresh());
            }

            return $payment->load('allocations', 'member');
        });
    }

    public function approve(Payment $payment, ?int $reviewedBy = null): Payment
    {
        if ($payment->status === self::STATUS_CONFIRMED) {
            return $payment->load('allocations', 'member');
        }

        if ($payment->status === self::STATUS_REJECTED) {
            throw ValidationException::withMessages([
                'status' => 'Rejected payments cannot be approved.',
            ]);
        }

        return DB::transaction(function () use ($payment, $reviewedBy) {
            $payment->load('allocations', 'member');

            foreach ($payment->allocations as $allocation) {
                if ($allocation->status === 'applied') {
                    continue;
                }

                $due = $allocation->monthly_due_id
                    ? MonthlyDue::query()->find($allocation->monthly_due_id)
                    : null;

                if (! $due) {
                    $month = Carbon::parse($allocation->billing_month)->startOfMonth();
                    $due = MonthlyDue::query()->firstOrCreate(
                        [
                            'member_id' => $payment->member_id,
                            'billing_month' => $month->toDateString(),
                        ],
                        [
                            'amount_due' => $payment->member->monthly_amount,
                            'amount_paid' => 0,
                            'status' => 'unpaid',
                        ]
                    );
                    $allocation->monthly_due_id = $due->id;
                }

                $due->amount_paid += (int) $allocation->amount;
                $due->refreshStatus();
                $allocation->update(['status' => 'applied']);
            }

            $payment->update([
                'status' => self::STATUS_CONFIRMED,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->recalculateMemberSummary($payment->member->fresh());

            return $payment->fresh()->load('allocations', 'member');
        });
    }

    public function reject(Payment $payment, ?string $reason = null, ?int $reviewedBy = null): Payment
    {
        if ($payment->status === self::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'status' => 'Confirmed payments cannot be rejected.',
            ]);
        }

        if ($payment->status === self::STATUS_REJECTED) {
            return $payment->load('allocations', 'member');
        }

        return DB::transaction(function () use ($payment, $reason, $reviewedBy) {
            $payment->allocations()->update(['status' => 'rejected']);
            $payment->update([
                'status' => self::STATUS_REJECTED,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            return $payment->fresh()->load('allocations', 'member');
        });
    }

    public function ensureDuesFromJoinDate(Member $member): void
    {
        $end = now()->startOfMonth();
        $start = $member->joined_at
            ? Carbon::parse($member->joined_at)->startOfMonth()
            : $end->copy();

        if ($start->greaterThan($end)) {
            $end = $start->copy();
        }

        $maxStart = $end->copy()->subMonths(35);
        if ($start->lessThan($maxStart)) {
            $start = $maxStart;
        }

        for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
            MonthlyDue::query()->firstOrCreate(
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
        }

        $this->recalculateMemberSummary($member->fresh());
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
