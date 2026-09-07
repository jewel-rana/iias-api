<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberJoinRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class JoinRequestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string'],
            'phone' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'referral_code' => ['nullable', 'string'],
        ]);

        $referredBy = null;
        if (! empty($data['referral_code'])) {
            $referredBy = Member::query()->where('referral_code', $data['referral_code'])->value('id');
        }

        $join = MemberJoinRequest::query()->create([
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'referred_by_member_id' => $referredBy,
            'status' => 'submitted',
        ]);

        return response()->json(['id' => (string) $join->id, 'status' => $join->status], 201);
    }

    public function index()
    {
        return response()->json([
            'data' => MemberJoinRequest::query()->latest()->get(),
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
                'monthly_amount' => $joinRequest->preferred_monthly_amount ?? 500,
                'collector_name' => $request->user()?->name,
                'status' => 'unpaid',
                'referral_code' => strtoupper(Str::substr(Str::slug($joinRequest->full_name, ''), 0, 2)).'-'.random_int(1000, 9999),
            ]);

            User::query()->updateOrCreate(
                ['phone' => preg_replace('/\D+/', '', $joinRequest->phone)],
                [
                    'name' => $joinRequest->full_name,
                    'email' => $joinRequest->email ?: preg_replace('/\D+/', '', $joinRequest->phone).'@ummah.local',
                    'password' => Hash::make('member'),
                    'role' => 'member',
                    'member_id' => $member->id,
                ]
            );

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
}
