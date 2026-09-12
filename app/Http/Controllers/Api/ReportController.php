<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MonthlyDue;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function monthly(Request $request)
    {
        $month = $this->parseMonth($request->input('month'));
        $end = $month->copy()->endOfMonth();

        $members = Member::query()
            ->where(function ($q) use ($end) {
                $q->whereNull('joined_at')->orWhereDate('joined_at', '<=', $end);
            })
            ->get();

        $dues = MonthlyDue::query()
            ->whereDate('billing_month', $month->toDateString())
            ->get()
            ->keyBy('member_id');

        $expected = 0;
        $collected = 0;
        $paidMembers = 0;
        $partialMembers = 0;
        $unpaidMembers = 0;

        foreach ($members as $member) {
            $due = $dues->get($member->id);
            $amountDue = (int) ($due?->amount_due ?? $member->monthly_amount);
            $amountPaid = (int) ($due?->amount_paid ?? 0);
            $status = $due?->status ?? 'unpaid';

            $expected += $amountDue;
            $collected += min($amountPaid, $amountDue);

            match ($status) {
                'paid' => $paidMembers++,
                'partial' => $partialMembers++,
                default => $unpaidMembers++,
            };
        }

        $advancePaidMembers = MonthlyDue::query()
            ->whereDate('billing_month', '>', $month->toDateString())
            ->where('status', 'paid')
            ->pluck('member_id')
            ->unique()
            ->count();

        return response()->json([
            'month_label' => $month->format('F Y'),
            'expected' => max($expected, 0),
            'collected' => $collected,
            'outstanding' => max($expected - $collected, 0),
            'paid_members' => $paidMembers,
            'partial_members' => $partialMembers,
            'unpaid_members' => $unpaidMembers,
            'advance_paid_members' => $advancePaidMembers,
        ]);
    }

    private function parseMonth(mixed $value): Carbon
    {
        $current = now()->startOfMonth();
        if (! filled($value)) {
            return $current;
        }

        try {
            $month = Carbon::parse((string) $value)->startOfMonth();
        } catch (\Throwable) {
            return $current;
        }

        return $month->gt($current) ? $current : $month;
    }
}
