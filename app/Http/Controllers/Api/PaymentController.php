<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventDonation;
use App\Models\Expense;
use App\Models\Member;
use App\Models\OrganizationSetting;
use App\Models\Payment;
use App\Services\PaymentAllocationService;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentAllocationService $service,
        private PushNotificationService $push,
    ) {}

    public function index(Request $request)
    {
        $query = Payment::query()->with(['allocations', 'member'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('member_id')) {
            $query->where('member_id', $request->integer('member_id'));
        }

        if ($request->filled('billing_month')) {
            try {
                $month = Carbon::parse((string) $request->input('billing_month'))->startOfMonth()->toDateString();
                $query->whereHas('allocations', fn ($q) => $q->whereDate('billing_month', $month));
            } catch (\Throwable) {
                // Ignore an invalid month filter.
            }
        }

        // Members only see their own payments.
        if ($request->user() && ! $request->user()->isStaff() && $request->user()->member_id) {
            $query->where('member_id', $request->user()->member_id);
        }

        $payments = $query->limit(200)->get();

        return response()->json([
            'data' => $payments->map(fn (Payment $p) => $this->transform($p)),
        ]);
    }

    public function show(Request $request, Payment $payment)
    {
        $user = $request->user();
        if ($user && ! $user->isStaff() && (int) $user->member_id !== (int) $payment->member_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($this->transform($payment->loadMissing(['allocations', 'member'])));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string'],
            'collector_name' => ['nullable', 'string', 'max:120'],
            'wallet_account' => ['nullable', 'string', 'max:40'],
            'transaction_reference' => ['nullable', 'string', 'max:80'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.billing_month' => ['required', 'date'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $isMemberSelf = $user && ! $user->isStaff();

        if ($isMemberSelf) {
            if (! $user->member_id || (int) $user->member_id !== (int) $data['member_id']) {
                return response()->json(['message' => 'Members can only submit their own payments.'], 403);
            }
        }

        $member = Member::query()->findOrFail($data['member_id']);
        $payment = $this->service->createPayment(
            member: $member,
            amount: $data['amount'],
            method: $data['payment_method'],
            allocations: $data['allocations'],
            createdBy: $user?->id,
            idempotencyKey: $data['idempotency_key'] ?? null,
            collectorName: $isMemberSelf
                ? ($data['collector_name'] ?? 'Self')
                : ($data['collector_name'] ?? $user?->name),
            walletAccount: $data['wallet_account'] ?? null,
            transactionReference: $data['transaction_reference'] ?? null,
            requiresApproval: $isMemberSelf,
            submittedByRole: $user?->role,
        );

        $payment->loadMissing('member');
        if ($isMemberSelf) {
            $this->push->notifyStaff(
                'Payment pending approval',
                ($payment->member?->name ?? 'A member').' submitted ৳'.number_format($payment->amount),
                ['type' => 'payment_pending', 'route' => '/payment-approvals'],
            );
        } else {
            $this->push->notifyMember(
                (int) $payment->member_id,
                'Payment recorded',
                '৳'.number_format($payment->amount).' received. Receipt '.$payment->receipt_number,
                ['type' => 'payment_confirmed', 'route' => '/member-home'],
            );
        }

        return response()->json($this->transform($payment), 201);
    }

    public function approve(Request $request, Payment $payment)
    {
        if (! $request->user()?->hasPermission('payments.approve')) {
            return response()->json(['message' => 'Only staff with approval permission can approve payments.'], 403);
        }

        $payment = $this->service->approve($payment, $request->user()?->id);
        $payment->loadMissing('member');
        $this->push->notifyMember(
            (int) $payment->member_id,
            'Payment confirmed',
            '৳'.number_format($payment->amount).' was approved. Receipt '.$payment->receipt_number,
            ['type' => 'payment_confirmed', 'route' => '/member-home'],
        );

        return response()->json($this->transform($payment));
    }

    public function destroy(Request $request, Payment $payment)
    {
        if (! $request->user()?->isStaff() || ! $request->user()?->hasPermission('collection.collect')) {
            return response()->json(['message' => 'Only staff can delete payments.'], 403);
        }

        $this->service->delete($payment);

        return response()->json(['ok' => true]);
    }

    public function reject(Request $request, Payment $payment)
    {
        if (! $request->user()?->hasPermission('payments.approve')) {
            return response()->json(['message' => 'Only staff with approval permission can reject payments.'], 403);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = $this->service->reject(
            $payment,
            $data['reason'] ?? null,
            $request->user()?->id,
        );
        $payment->loadMissing('member');
        $this->push->notifyMember(
            (int) $payment->member_id,
            'Payment rejected',
            $data['reason'] ?: ('Receipt '.$payment->receipt_number.' was rejected.'),
            ['type' => 'payment_rejected', 'route' => '/member-home'],
        );

        return response()->json($this->transform($payment));
    }

    public function dashboard()
    {
        $members = Member::query()->get();
        $expected = (int) $members->sum('monthly_amount');
        $confirmedStatuses = ['confirmed', 'completed'];

        $collected = (int) Payment::query()
            ->whereIn('status', $confirmedStatuses)
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        $opening = (int) (OrganizationSetting::current()->opening_fund_balance ?? 0);
        $totalInflow = $opening
            + (int) Payment::query()->whereIn('status', $confirmedStatuses)->sum('amount')
            + (int) EventDonation::query()->sum('amount');
        $totalExpenses = (int) Expense::query()->sum('amount');
        $pendingCount = (int) Payment::query()
            ->where('status', PaymentAllocationService::STATUS_PENDING)
            ->count();

        return response()->json([
            'expected' => $expected,
            'collected' => $collected,
            'total_members' => $members->count(),
            'paid' => $members->where('status', 'paid')->count(),
            'partial' => $members->where('status', 'partial')->count(),
            'unpaid' => $members->where('status', 'unpaid')->count(),
            'total_inflow' => $totalInflow,
            'total_expenses' => $totalExpenses,
            'funds_available' => max($totalInflow - $totalExpenses, 0),
            'pending_payments' => $pendingCount,
        ]);
    }

    private function transform(Payment $p): array
    {
        return [
            'id' => (string) $p->id,
            'receipt_number' => $p->receipt_number,
            'member_id' => (string) $p->member_id,
            'member_name' => $p->member?->name,
            'amount' => $p->amount,
            'payment_method' => $p->payment_method,
            'collector_name' => $p->collector_name,
            'wallet_account' => $p->wallet_account,
            'transaction_reference' => $p->transaction_reference,
            'payment_date' => $p->payment_date?->toIso8601String(),
            'status' => $p->status,
            'submitted_by_role' => $p->submitted_by_role,
            'rejection_reason' => $p->rejection_reason,
            'reviewed_at' => $p->reviewed_at?->toIso8601String(),
            'allocations' => $p->allocations->map(fn ($a) => [
                'billing_month' => $a->billing_month->toDateString(),
                'amount' => $a->amount,
                'status' => $a->status,
            ])->values(),
        ];
    }
}
