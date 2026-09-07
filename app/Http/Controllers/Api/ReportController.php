<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Payment;

class ReportController extends Controller
{
    public function monthly()
    {
        $members = Member::query()->get();
        $collected = (int) Payment::query()
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
        $expected = max($members->sum('monthly_amount'), 1);

        return response()->json([
            'month_label' => now()->format('F Y'),
            'expected' => $expected,
            'collected' => $collected,
            'outstanding' => max($expected - $collected, 0),
            'paid_members' => $members->where('status', 'paid')->count(),
            'partial_members' => $members->where('status', 'partial')->count(),
            'unpaid_members' => $members->where('status', 'unpaid')->count(),
            'advance_paid_members' => $members->where('advance_months', '>', 0)->count(),
        ]);
    }
}
