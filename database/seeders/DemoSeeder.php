<?php

namespace Database\Seeders;

use App\Models\CommitteeMember;
use App\Models\CommitteeRole;
use App\Models\EventDonation;
use App\Models\Expense;
use App\Models\ExpenseHead;
use App\Models\FundraisingEvent;
use App\Models\Member;
use App\Models\MemberJoinRequest;
use App\Models\MonthlyDue;
use App\Models\OrganizationSetting;
use App\Models\Payment;
use App\Models\PaymentAllocation;
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

        OrganizationSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'organization_name' => 'IIAS',
                'tagline' => 'Isapura Islamic United Organization',
                'contact_phone' => '01911785317',
                'address' => 'Isapura Molla Bari Jame Masjid, Bakergonj, Barisal',
                'default_monthly_amount' => 500,
                'currency_symbol' => '৳',
                'referral_enabled' => true,
                'public_join_enabled' => true,
                'opening_fund_balance' => 562500,
            ]
        );

        $memberRows = [
            // name, phone, code, ref, status, outstanding, advance, dueMonths, advanceMonths, totalPaid, monthly
            ['Abdul Karim', '01811112222', 'M-001024', 'AK-1024', 'unpaid', 1000, 1000, 2, 2, 5000, 500, 'Rahim Ahmed'],
            ['Rahim Ahmed', '01933334444', 'M-001025', 'RA-1025', 'paid', 0, 1500, 0, 3, 6000, 500, 'Hasan Ali'],
            ['Fatima Begum', '01655556666', 'M-001026', 'FB-1026', 'partial', 700, 0, 2, 0, 4300, 500, 'Rahim Ahmed'],
            ['Nazmul Hossain', '01577778888', 'M-001027', 'NH-1027', 'unpaid', 2000, 0, 2, 0, 3000, 1000, 'Hasan Ali'],
            ['Salma Akter', '01399990000', 'M-001028', 'SA-1028', 'paid', 0, 500, 0, 1, 5500, 500, 'Rahim Ahmed'],
        ];

        $membersByPhone = [];

        foreach ($memberRows as $row) {
            [$name, $phone, $code, $ref, $status, $outstanding, $advance, $dueMonths, $advanceMonths, $totalPaid, $monthly, $collector] = $row;

            $member = Member::query()->updateOrCreate(
                ['phone' => $phone],
                [
                    'member_code' => $code,
                    'name' => $name,
                    'monthly_amount' => $monthly,
                    'collector_name' => $collector,
                    'status' => $status,
                    'total_paid' => $totalPaid,
                    'outstanding' => $outstanding,
                    'advance' => $advance,
                    'paid_months' => max(0, 12 - $dueMonths),
                    'due_months' => $dueMonths,
                    'advance_months' => $advanceMonths,
                    'referral_code' => $ref,
                ]
            );

            $membersByPhone[$phone] = $member;

            MonthlyDue::query()->where('member_id', $member->id)->delete();

            foreach ([7, 8, 9, 10, 11, 12] as $month) {
                $billing = Carbon::create(2026, $month, 1);
                $amount = $member->monthly_amount;
                $paid = $amount;
                $dueStatus = 'paid';

                if ($month === 8 && $status === 'partial') {
                    $paid = 300;
                    $dueStatus = 'partial';
                } elseif ($month === 9) {
                    if ($status === 'paid') {
                        $paid = $amount;
                        $dueStatus = 'paid';
                    } else {
                        $paid = 0;
                        $dueStatus = 'unpaid';
                    }
                } elseif ($month === 10) {
                    $paid = $advanceMonths > 0 ? $amount : 0;
                    $dueStatus = $advanceMonths > 0 ? 'paid' : 'unpaid';
                } elseif ($month === 11) {
                    $paid = $advanceMonths > 1 ? $amount : 0;
                    $dueStatus = $advanceMonths > 1 ? 'paid' : 'unpaid';
                } elseif ($month === 12) {
                    $paid = 0;
                    $dueStatus = 'unpaid';
                } elseif ($month < 8) {
                    $paid = $amount;
                    $dueStatus = 'paid';
                }

                MonthlyDue::query()->create([
                    'member_id' => $member->id,
                    'billing_month' => $billing,
                    'amount_due' => $amount,
                    'amount_paid' => $paid,
                    'status' => $dueStatus,
                ]);
            }
        }

        $abdul = $membersByPhone['01811112222'];
        User::query()->updateOrCreate(
            ['email' => 'member@ummah.local'],
            [
                'name' => $abdul->name,
                'phone' => $abdul->phone,
                'password' => Hash::make('member'),
                'role' => 'member',
                'member_id' => $abdul->id,
            ]
        );

        $flood = FundraisingEvent::query()->updateOrCreate(
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

        $heads = [
            ["Imam's Salary", 'imam_salary', 'salary', 'monthly', 1],
            ['Staff Salary', 'staff_salary', 'salary', 'monthly', 2],
            ['Festival Bonus (Eid)', 'festival_bonus_eid', 'festival_bonus', 'occasional', 3],
            ['Festival Bonus (other)', 'festival_bonus_other', 'festival_bonus', 'occasional', 4],
            ['Social Curriculum', 'social_curriculum', 'operational', 'monthly', 5],
            ['Utilities', 'utilities', 'operational', 'monthly', 6],
            ['Maintenance', 'maintenance', 'operational', 'occasional', 7],
            ['Charity / Relief', 'charity', 'charity', 'occasional', 8],
            ['Other', 'other', 'other', 'occasional', 9],
        ];

        $headsByCode = [];
        foreach ($heads as $row) {
            [$name, $code, $kind, $recurrence, $sort] = $row;
            $headsByCode[$code] = ExpenseHead::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'kind' => $kind,
                    'default_recurrence' => $recurrence,
                    'is_active' => true,
                    'sort_order' => $sort,
                ]
            );
        }

        $expenses = [
            ["Imam's Salary — September", 'imam_salary', 'monthly', 15000, '2026-09-01', 'cash_to_collector', null],
            ["Imam's Salary — August", 'imam_salary', 'monthly', 15000, '2026-08-01', 'cash_to_collector', null],
            ['Eid-ul-Fitr Festival Bonus', 'festival_bonus_eid', 'occasional', 10000, '2026-04-10', 'cash_to_collector', 'Imam + helpers'],
            ['Weekend Islamic class materials', 'social_curriculum', 'monthly', 3500, '2026-09-05', null, 'Books and stationery for kids class'],
            ['Eid food distribution', 'charity', 'occasional', 12000, '2026-08-20', null, null],
            ['Mosque cleaning supplies', 'maintenance', 'occasional', 2200, '2026-09-03', null, null],
            ['Electricity bill', 'utilities', 'monthly', 4800, '2026-09-02', 'mobile_wallet', null],
        ];

        foreach ($expenses as $row) {
            [$title, $category, $recurrence, $amount, $date, $method, $notes] = $row;
            $head = $headsByCode[$category] ?? $headsByCode['other'];
            Expense::query()->updateOrCreate(
                ['title' => $title, 'expense_date' => $date],
                [
                    'expense_head_id' => $head->id,
                    'category' => $head->code,
                    'recurrence' => $recurrence,
                    'amount' => $amount,
                    'payment_method' => $method,
                    'notes' => $notes,
                    'created_by' => $admin->id,
                ]
            );
        }

        Payment::query()->whereIn('receipt_number', ['P-10025', 'P-10018'])->get()->each(function (Payment $p) {
            $p->allocations()->delete();
            $p->delete();
        });

        $rahim = $membersByPhone['01933334444'];

        $p1 = Payment::query()->create([
            'receipt_number' => 'P-10025',
            'member_id' => $abdul->id,
            'amount' => 3000,
            'payment_method' => 'cash_to_collector',
            'collector_name' => 'Rahim Ahmed',
            'payment_date' => Carbon::create(2026, 9, 7, 10),
            'status' => 'confirmed',
            'idempotency_key' => 'seed-p-10025',
            'created_by' => $admin->id,
            'submitted_by_role' => 'admin',
        ]);

        foreach ([
            [2026, 9], [2026, 10], [2026, 11], [2026, 12], [2027, 1], [2027, 2],
        ] as [$y, $m]) {
            $billing = Carbon::create($y, $m, 1);
            $due = MonthlyDue::query()
                ->where('member_id', $abdul->id)
                ->whereDate('billing_month', $billing)
                ->first();

            PaymentAllocation::query()->create([
                'payment_id' => $p1->id,
                'member_id' => $abdul->id,
                'monthly_due_id' => $due?->id,
                'billing_month' => $billing,
                'amount' => 500,
                'status' => 'applied',
            ]);
        }

        $p2 = Payment::query()->create([
            'receipt_number' => 'P-10018',
            'member_id' => $rahim->id,
            'amount' => 500,
            'payment_method' => 'mobile_wallet',
            'wallet_account' => '01712345678',
            'transaction_reference' => 'TXN-SEED-10018',
            'payment_date' => Carbon::create(2026, 9, 5, 14),
            'status' => 'confirmed',
            'idempotency_key' => 'seed-p-10018',
            'created_by' => $admin->id,
            'submitted_by_role' => 'admin',
        ]);

        $rahimDue = MonthlyDue::query()
            ->where('member_id', $rahim->id)
            ->whereDate('billing_month', '2026-09-01')
            ->first();

        PaymentAllocation::query()->create([
            'payment_id' => $p2->id,
            'member_id' => $rahim->id,
            'monthly_due_id' => $rahimDue?->id,
            'billing_month' => Carbon::create(2026, 9, 1),
            'amount' => 500,
            'status' => 'applied',
        ]);

        EventDonation::query()->updateOrCreate(
            ['idempotency_key' => 'seed-d-20011'],
            [
                'receipt_number' => 'D-20011',
                'fundraising_event_id' => $flood->id,
                'donor_type' => 'non_member',
                'donor_name' => 'Karim Hossain',
                'donor_phone' => '01700001111',
                'referred_by_member_id' => $abdul->id,
                'amount' => 2000,
                'payment_method' => 'mobile_wallet',
                'payment_date' => Carbon::create(2026, 9, 6, 11),
                'status' => 'completed',
                'created_by' => $admin->id,
            ]
        );

        $joinRows = [
            ['Imran Hossain', '01755556666', 'imran@example.com', 'AK-1024', 500, 'submitted', '2026-09-06 10:20:00', null],
            ['Nusrat Jahan', '01877778888', null, null, 500, 'submitted', '2026-09-05 16:45:00', null],
            ['Shahidul Islam', '01922223333', null, 'RA-1025', 1000, 'submitted', '2026-09-04 09:10:00', null],
            ['Rasheda Begum', '01611112222', null, null, 500, 'approved', '2026-09-01 11:00:00', null],
            ['Kamrul Hasan', '01533334444', null, null, null, 'rejected', '2026-08-28 14:30:00', 'Could not verify phone number'],
        ];

        foreach ($joinRows as $row) {
            [$fullName, $phone, $email, $ref, $amount, $status, $created, $reason] = $row;
            $referredBy = $ref
                ? Member::query()->where('referral_code', $ref)->value('id')
                : null;

            MemberJoinRequest::query()->updateOrCreate(
                ['phone' => $phone, 'full_name' => $fullName],
                [
                    'email' => $email,
                    'preferred_monthly_amount' => $amount,
                    'referral_code' => $ref,
                    'referred_by_member_id' => $referredBy,
                    'status' => $status,
                    'rejection_reason' => $reason,
                    'reviewed_by' => in_array($status, ['approved', 'rejected'], true) ? $admin->id : null,
                    'reviewed_at' => in_array($status, ['approved', 'rejected'], true) ? Carbon::parse($created)->addDay() : null,
                    'created_at' => Carbon::parse($created),
                    'updated_at' => Carbon::parse($created),
                ]
            );
        }

        $roleRows = [
            ['Chairman', 'chairman', 1],
            ['Secretary', 'secretary', 2],
            ['Treasurer', 'treasurer', 3],
            ['Joint Secretary', 'joint_secretary', 4],
            ['Organizing Member', 'organizing_member', 5],
        ];
        $rolesByCode = [];
        foreach ($roleRows as $row) {
            [$name, $code, $sort] = $row;
            $rolesByCode[$code] = CommitteeRole::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'sort_order' => $sort,
                    'is_active' => true,
                ]
            );
        }

        $rahimMember = $membersByPhone['01933334444'];
        $committeePeople = [
            ['Ahmad Rahman', '01712345678', 'admin@ummah.local', 'chairman', null, 1],
            ['Rahim Ahmed', '01933334444', null, 'secretary', $rahimMember->id, 2],
            ['Hasan Ali', '01788889999', null, 'treasurer', null, 3],
            ['Fatima Begum', '01655556666', null, 'joint_secretary', $membersByPhone['01655556666']->id, 4],
            ['Nazmul Hossain', '01577778888', null, 'organizing_member', $membersByPhone['01577778888']->id, 5],
        ];

        foreach ($committeePeople as $row) {
            [$name, $phone, $email, $roleCode, $memberId, $sort] = $row;
            CommitteeMember::query()->updateOrCreate(
                ['phone' => $phone, 'committee_role_id' => $rolesByCode[$roleCode]->id],
                [
                    'name' => $name,
                    'email' => $email,
                    'member_id' => $memberId,
                    'is_active' => true,
                    'sort_order' => $sort,
                ]
            );
        }
    }
}
