<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\MonthlyDue;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberPaymentEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_member_role_and_monthly_amount(): void
    {
        Sanctum::actingAs($this->admin());
        $member = $this->member(['joined_at' => now()->startOfMonth()->toDateString()]);
        $this->getJson('/api/v1/members/'.$member->id.'/dues')->assertOk();

        $collectorId = Role::query()->where('code', 'collector')->value('id');

        $this->putJson('/api/v1/members/'.$member->id, [
            'role_id' => $collectorId,
            'monthly_amount' => 800,
        ])->assertOk()
            ->assertJsonPath('role_code', 'collector')
            ->assertJsonPath('monthly_amount', 800);

        $this->assertSame(800, (int) $member->dues()->first()->amount_due);
        $this->assertSame(800, (int) $member->fresh()->outstanding);
    }

    public function test_monthly_amount_change_does_not_rewrite_paid_dues(): void
    {
        Sanctum::actingAs($this->admin());
        $member = $this->member(['joined_at' => now()->startOfMonth()->toDateString()]);

        $this->postJson('/api/v1/payments', [
            'member_id' => $member->id,
            'amount' => 500,
            'payment_method' => 'cash_to_collector',
            'allocations' => [[
                'billing_month' => now()->startOfMonth()->toDateString(),
                'amount' => 500,
            ]],
        ])->assertCreated();

        $this->putJson('/api/v1/members/'.$member->id, [
            'monthly_amount' => 800,
        ])->assertOk();

        $due = MonthlyDue::query()
            ->where('member_id', $member->id)
            ->whereDate('billing_month', now()->startOfMonth())
            ->first();

        $this->assertSame(500, (int) $due->amount_due);
        $this->assertSame('paid', $due->status);
        $this->assertSame(800, (int) $member->fresh()->monthly_amount);
    }

    public function test_role_change_updates_linked_user_account(): void
    {
        Sanctum::actingAs($this->admin());
        $member = $this->member();
        $user = User::factory()->create([
            'name' => $member->name,
            'phone' => $member->phone,
            'role' => 'member',
            'role_id' => Role::memberId(),
            'member_id' => $member->id,
        ]);
        $collectorId = Role::query()->where('code', 'collector')->value('id');

        $this->putJson('/api/v1/members/'.$member->id, [
            'role_id' => $collectorId,
        ])->assertOk();

        $this->assertSame('collector', $user->fresh()->role);
        $this->assertSame($collectorId, $user->fresh()->role_id);
    }

    public function test_member_cannot_update_another_member(): void
    {
        $member = $this->member();
        $actor = User::factory()->create([
            'role' => 'member',
            'role_id' => Role::memberId(),
            'phone' => '01911112222',
            'member_id' => $member->id,
        ]);
        Sanctum::actingAs($actor);

        $this->putJson('/api/v1/members/'.$member->id, [
            'monthly_amount' => 900,
        ])->assertForbidden();
    }

    public function test_admin_can_delete_a_confirmed_payment_and_reverse_dues(): void
    {
        Sanctum::actingAs($this->admin());
        $member = $this->member(['joined_at' => now()->startOfMonth()->toDateString()]);

        $paymentId = $this->postJson('/api/v1/payments', [
            'member_id' => $member->id,
            'amount' => 500,
            'payment_method' => 'cash_to_collector',
            'allocations' => [[
                'billing_month' => now()->startOfMonth()->toDateString(),
                'amount' => 500,
            ]],
        ])->assertCreated()->json('id');

        $this->assertSame(500, (int) $member->fresh()->total_paid);

        $this->deleteJson('/api/v1/payments/'.$paymentId)->assertOk();

        $this->assertDatabaseMissing('payments', ['id' => $paymentId]);
        $this->assertSame(0, (int) $member->fresh()->total_paid);
        $due = MonthlyDue::query()
            ->where('member_id', $member->id)
            ->whereDate('billing_month', now()->startOfMonth())
            ->first();
        $this->assertSame(0, (int) $due->amount_paid);
        $this->assertSame('unpaid', $due->status);
    }

    public function test_member_cannot_delete_a_payment(): void
    {
        Sanctum::actingAs($this->admin());
        $member = $this->member(['joined_at' => now()->startOfMonth()->toDateString()]);
        $paymentId = $this->postJson('/api/v1/payments', [
            'member_id' => $member->id,
            'amount' => 500,
            'payment_method' => 'cash_to_collector',
            'allocations' => [[
                'billing_month' => now()->startOfMonth()->toDateString(),
                'amount' => 500,
            ]],
        ])->assertCreated()->json('id');

        $actor = User::factory()->create([
            'role' => 'member',
            'role_id' => Role::memberId(),
            'phone' => '01911112223',
            'member_id' => $member->id,
        ]);
        Sanctum::actingAs($actor);

        $this->deleteJson('/api/v1/payments/'.$paymentId)->assertForbidden();
        $this->assertDatabaseHas('payments', ['id' => $paymentId]);
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

    private function member(array $overrides = []): Member
    {
        return Member::query()->create(array_merge([
            'member_code' => 'M-001099',
            'name' => 'Test Member',
            'phone' => '01911785317',
            'email' => 'member@example.com',
            'monthly_amount' => 500,
            'collector_name' => 'Admin',
            'status' => 'unpaid',
            'total_paid' => 0,
            'outstanding' => 500,
            'advance' => 0,
            'paid_months' => 0,
            'due_months' => 1,
            'advance_months' => 0,
            'referral_code' => 'TM-1099',
            'role_id' => Role::memberId(),
        ], $overrides));
    }
}
