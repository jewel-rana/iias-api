<?php

namespace Database\Seeders;

use App\Models\FundraisingEvent;
use App\Models\Member;
use App\Models\MonthlyDue;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@ummah.local'],
            [
                'name' => 'Ahmad Rahman',
                'phone' => '01712345678',
                'password' => Hash::make('admin'),
                'role' => 'admin',
            ]
        );

        $members = [
            ['Abdul Karim', '01811112222', 'AK-1024', 'unpaid', 1000, 1000, 2, 2],
            ['Rahim Ahmed', '01933334444', 'RA-1025', 'paid', 0, 1500, 0, 3],
            ['Fatima Begum', '01655556666', 'FB-1026', 'partial', 700, 0, 2, 0],
            ['Nazmul Hossain', '01577778888', 'NH-1027', 'unpaid', 2000, 0, 2, 0],
            ['Salma Akter', '01399990000', 'SA-1028', 'paid', 0, 500, 0, 1],
        ];

        foreach ($members as $i => $row) {
            [$name, $phone, $ref, $status, $outstanding, $advance, $dueMonths, $advanceMonths] = $row;
            $member = Member::query()->updateOrCreate(
                ['phone' => $phone],
                [
                    'member_code' => 'M-00'.(1024 + $i),
                    'name' => $name,
                    'monthly_amount' => $i === 3 ? 1000 : 500,
                    'collector_name' => $i % 2 === 0 ? 'Rahim Ahmed' : 'Hasan Ali',
                    'status' => $status,
                    'total_paid' => 5000,
                    'outstanding' => $outstanding,
                    'advance' => $advance,
                    'paid_months' => 8,
                    'due_months' => $dueMonths,
                    'advance_months' => $advanceMonths,
                    'referral_code' => $ref,
                ]
            );

            foreach (range(7, 12) as $month) {
                $billing = Carbon::create(2026, $month, 1);
                $amount = $member->monthly_amount;
                $paid = $amount;
                $dueStatus = 'paid';

                if ($member->status === 'unpaid' && $month >= 9) {
                    $paid = 0;
                    $dueStatus = 'unpaid';
                }
                if ($member->status === 'partial' && $month === 8) {
                    $paid = 300;
                    $dueStatus = 'partial';
                }
                if ($member->status === 'partial' && $month >= 9) {
                    $paid = 0;
                    $dueStatus = 'unpaid';
                }
                if ($member->status === 'paid' && $month <= 10) {
                    $paid = $amount;
                    $dueStatus = 'paid';
                }

                MonthlyDue::query()->updateOrCreate(
                    [
                        'member_id' => $member->id,
                        'billing_month' => $billing->toDateString(),
                    ],
                    [
                        'amount_due' => $amount,
                        'amount_paid' => $paid,
                        'status' => $dueStatus,
                    ]
                );
            }

            if ($i === 0) {
                User::query()->updateOrCreate(
                    ['email' => 'member@ummah.local'],
                    [
                        'name' => $name,
                        'phone' => $phone,
                        'password' => Hash::make('member'),
                        'role' => 'member',
                        'member_id' => $member->id,
                    ]
                );
            }
        }

        FundraisingEvent::query()->updateOrCreate(
            ['slug' => 'flood-relief-2026'],
            [
                'title' => 'Flood Relief 2026',
                'description' => 'Emergency support for flood-affected families.',
                'goal_amount' => 100000,
                'raised_amount' => 62400,
                'donor_count' => 86,
                'starts_at' => Carbon::create(2026, 8, 1),
                'ends_at' => Carbon::create(2026, 10, 31),
                'status' => 'active',
                'created_by' => $admin->id,
            ]
        );

        FundraisingEvent::query()->updateOrCreate(
            ['slug' => 'winter-relief-2026'],
            [
                'title' => 'Winter Relief Drive',
                'description' => 'Warm clothing and blankets for rural communities.',
                'goal_amount' => 80000,
                'raised_amount' => 21500,
                'donor_count' => 34,
                'starts_at' => Carbon::create(2026, 9, 1),
                'ends_at' => Carbon::create(2026, 12, 15),
                'status' => 'active',
                'created_by' => $admin->id,
            ]
        );

        FundraisingEvent::query()->updateOrCreate(
            ['slug' => 'mosque-renovation'],
            [
                'title' => 'Mosque Renovation',
                'goal_amount' => 250000,
                'raised_amount' => 250000,
                'donor_count' => 210,
                'starts_at' => Carbon::create(2026, 1, 1),
                'ends_at' => Carbon::create(2026, 6, 30),
                'status' => 'closed',
                'created_by' => $admin->id,
            ]
        );
    }
}
