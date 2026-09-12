<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Services\PaymentAllocationService;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(private PaymentAllocationService $dues) {}
    public function index(Request $request)
    {
        $query = Member::query()->orderBy('name');

        if ($search = $request->string('query')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('member_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Member $m) => $this->transform($m)),
        ]);
    }

    public function show(Member $member)
    {
        return response()->json($this->transform($member));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'unique:members,phone'],
            'email' => ['nullable', 'email', 'max:120', 'unique:members,email'],
            'monthly_amount' => ['required', 'integer', 'min:1'],
            'collector_name' => ['nullable', 'string', 'max:120'],
            'joined_at' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $count = Member::query()->count() + 1024;
        $initials = collect(preg_split('/\s+/', trim($data['name'])) ?: [])
            ->filter()
            ->take(2)
            ->map(fn ($p) => strtoupper(substr($p, 0, 1)))
            ->implode('');

        $joinedAt = $data['joined_at'];
        $member = Member::query()->create([
            'member_code' => 'M-'.str_pad((string) $count, 6, '0', STR_PAD_LEFT),
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'monthly_amount' => $data['monthly_amount'],
            'collector_name' => $data['collector_name'] ?? 'Unassigned',
            'joined_at' => $joinedAt,
            'status' => 'unpaid',
            'total_paid' => 0,
            'outstanding' => $data['monthly_amount'],
            'advance' => 0,
            'paid_months' => 0,
            'due_months' => 1,
            'advance_months' => 0,
            'referral_code' => ($initials !== '' ? $initials : 'MB').'-'.$count,
        ]);

        $this->dues->ensureDuesFromJoinDate($member->fresh());

        return response()->json($this->transform($member->fresh()), 201);
    }

    public function dues(Member $member)
    {
        $this->dues->ensureDuesFromJoinDate($member);

        $dues = $member->dues()->orderBy('billing_month')->get();

        return response()->json([
            'data' => $dues->map(fn ($d) => [
                'id' => (string) $d->id,
                'member_id' => (string) $d->member_id,
                'billing_month' => $d->billing_month->toDateString(),
                'amount_due' => $d->amount_due,
                'amount_paid' => $d->amount_paid,
                'status' => $d->status,
            ]),
        ]);
    }

    private function transform(Member $m): array
    {
        return [
            'id' => (string) $m->id,
            'member_code' => $m->member_code,
            'name' => $m->name,
            'phone' => $m->phone,
            'email' => $m->email,
            'monthly_amount' => $m->monthly_amount,
            'collector_name' => $m->collector_name,
            'joined_at' => $m->joined_at?->toDateString(),
            'status' => $m->status,
            'total_paid' => $m->total_paid,
            'outstanding' => $m->outstanding,
            'advance' => $m->advance,
            'paid_months' => $m->paid_months,
            'due_months' => $m->due_months,
            'advance_months' => $m->advance_months,
            'referral_code' => $m->referral_code,
        ];
    }
}
