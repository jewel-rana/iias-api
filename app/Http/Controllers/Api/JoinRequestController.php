<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberJoinRequest;
use App\Models\Role;
use App\Services\PaymentAllocationService;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JoinRequestController extends Controller
{
    public function __construct(
        private PaymentAllocationService $dues,
        private PushNotificationService $push,
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'email' => ['required', 'email'],
            'referral_code' => ['nullable', 'string'],
            'preferred_monthly_amount' => ['nullable', 'integer', 'min:1'],
        ]);

        $referredBy = null;
        if (! empty($data['referral_code'])) {
            $referredBy = Member::query()->where('referral_code', $data['referral_code'])->value('id');
        }

        $join = MemberJoinRequest::query()->create([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'preferred_monthly_amount' => $data['preferred_monthly_amount'] ?? null,
            'referral_code' => $data['referral_code'] ?? null,
            'referred_by_member_id' => $referredBy,
            'status' => 'submitted',
        ]);

        $this->push->notifyStaff(
            'New join request',
            $join->full_name.' asked to join.',
            ['type' => 'join_request', 'route' => '/join-requests'],
        );

        return response()->json($this->transform($join), 201);
    }

    public function index(Request $request)
    {
        $query = MemberJoinRequest::query()->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'data' => $query->get()->map(fn (MemberJoinRequest $j) => $this->transform($j)),
        ]);
    }

    public function approve(Request $request, MemberJoinRequest $joinRequest)
    {
        if ($joinRequest->status === 'approved') {
            return response()->json(['message' => 'Already approved']);
        }

        DB::transaction(function () use ($joinRequest, $request) {
            $code = 'M-'.str_pad((string) (Member::query()->count() + 1024), 6, '0', STR_PAD_LEFT);
            $member = Member::query()->create([
                'member_code' => $code,
                'name' => $joinRequest->full_name,
                'phone' => $joinRequest->phone,
                'email' => $joinRequest->email,
                'role_id' => Role::memberId(),
                'monthly_amount' => $joinRequest->preferred_monthly_amount ?? 500,
                'collector_name' => $request->user()?->name ?? 'Unassigned',
                'joined_at' => now()->toDateString(),
                'status' => 'unpaid',
                'total_paid' => 0,
                'outstanding' => $joinRequest->preferred_monthly_amount ?? 500,
                'advance' => 0,
                'paid_months' => 0,
                'due_months' => 1,
                'advance_months' => 0,
                'referral_code' => strtoupper(Str::substr(Str::slug($joinRequest->full_name, ''), 0, 2)).'-'.random_int(1000, 9999),
            ]);

            $this->dues->ensureDuesFromJoinDate($member->fresh());

            $joinRequest->update([
                'status' => 'approved',
                'reviewed_by' => $request->user()?->id,
                'reviewed_at' => now(),
            ]);
        });

        return response()->json(['message' => 'Approved']);
    }

    public function reject(Request $request, MemberJoinRequest $joinRequest)
    {
        $data = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $joinRequest->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'] ?? null,
            'reviewed_by' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['message' => 'Rejected']);
    }

    private function transform(MemberJoinRequest $j): array
    {
        return [
            'id' => (string) $j->id,
            'full_name' => $j->full_name,
            'phone' => $j->phone,
            'email' => $j->email,
            'preferred_monthly_amount' => $j->preferred_monthly_amount,
            'referral_code' => $j->referral_code,
            'status' => $j->status,
            'rejection_reason' => $j->rejection_reason,
            'created_at' => $j->created_at?->toIso8601String(),
        ];
    }
}
