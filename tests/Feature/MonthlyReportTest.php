<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MonthlyDue;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_can_switch_months(): void
    {
        Sanctum::actingAs($this->admin());
        $member = $this->member();

        MonthlyDue::query()->create([
            'member_id' => $member->id,
            'billing_month' => '2026-08-01',
            'amount_due' => 500,
            'amount_paid' => 500,
            'status' => 'paid',
        ]);
        MonthlyDue::query()->create([
            'member_id' => $member->id,
            'billing_month' => '2026-09-01',
            'amount_due' => 500,
            'amount_paid' => 0,
            'status' => 'unpaid',
        ]);

        $this->getJson('/api/v1/reports/monthly-collection?month=2026-08')
            ->assertOk()
            ->assertJsonPath('month_label', 'August 2026')
            ->assertJsonPath('expected', 500)
            ->assertJsonPath('collected', 500)
            ->assertJsonPath('paid_members', 1)
            ->assertJsonPath('unpaid_members', 0);

        $this->getJson('/api/v1/reports/monthly-collection?month=2026-09')
            ->assertOk()
            ->assertJsonPath('month_label', 'September 2026')
            ->assertJsonPath('collected', 0)
            ->assertJsonPath('paid_members', 0)
            ->assertJsonPath('unpaid_members', 1);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@ummah.local',
            'phone' => '01712345678',
            'password' => 'admin',
            'role' => 'admin',
            'role_id' => Role::query()->where('code', 'admin')->value('id'),
        ]);
    }

    private function member(): Member
    {
        return Member::query()->create([
            'member_code' => 'M-001099',
            'name' => 'Test Member',
            'phone' => '01911785317',
            'email' => 'member@example.com',
            'monthly_amount' => 500,
            'collector_name' => 'Admin',
            'joined_at' => '2026-01-01',
            'status' => 'unpaid',
            'total_paid' => 0,
            'outstanding' => 500,
            'advance' => 0,
            'paid_months' => 0,
            'due_months' => 1,
            'advance_months' => 0,
            'referral_code' => 'TM-1099',
        ]);
    }
}
