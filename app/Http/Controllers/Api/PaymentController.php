<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Payment;
use App\Services\PaymentAllocationService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentAllocationService $service) {}

    public function index()
    {
        $payments = Payment::query()->with(['allocations', 'member'])->latest()->limit(50)->get();

        return response()->json([
            'data' => $payments->map(fn (Payment $p) => $this->transform($p)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.billing_month' => ['required', 'date'],
            'allocations.*.amount' => ['required', 'integer', 'min:1'],
        ]);

        $member = Member::query()->findOrFail($data['member_id']);
        $payment = $this->service->createPayment(
            member: $member,
            amount: $data['amount'],
            method: $data['payment_method'],
            allocations: $data['allocations'],
            createdBy: $request->user()?->id,
            idempotencyKey: $data['idempotency_key'] ?? null,
            collectorName: $request->user()?->name,
        );

        return response()->json($this->transform($payment), 201);
    }

    public function dashboard()
    {
        $members = Member::query()->get();
        $expected = $members->sum('monthly_amount');
        $collected = (int) Payment::query()
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        return response()->json([
            'expected' => max($expected, 62500),
            'collected' => max($collected, 0),
            'total_members' => $members->count(),
            'paid' => $members->where('status', 'paid')->count(),
            'partial' => $members->where('status', 'partial')->count(),
            'unpaid' => $members->where('status', 'unpaid')->count(),
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
            'payment_date' => $p->payment_date?->toIso8601String(),
            'allocations' => $p->allocations->map(fn ($a) => [
                'billing_month' => $a->billing_month->toDateString(),
                'amount' => $a->amount,
            ])->values(),
        ];
    }
}
