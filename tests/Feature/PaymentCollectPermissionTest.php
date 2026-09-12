<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentCollectPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_staff_role_can_collect_for_another_member(): void
    {
        $role = Role::query()->create([
            'name' => 'Treasurer',
            'code' => 'treasurer',
            'is_system' => false,
            'is_active' => true,
            'sort_order' => 10,
            'permissions' => [Permissions::COLLECTION_COLLECT],
        ]);

        $self = $this->member([
            'phone' => '01900000001',
            'email' => 'self@example.com',
            'member_code' => 'M-2001',
            'referral_code' => 'SL-2001',
        ]);
        $target = $this->member([
            'phone' => '01900000002',
            'email' => 'target@example.com',
            'member_code' => 'M-2002',
            'referral_code' => 'TG-2002',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'treasurer',
            'role_id' => $role->id,
            'phone' => '01800000001',
            'member_id' => $self->id,
        ]));

        $this->postJson('/api/v1/payments', $this->payload($target->id))
            ->assertCreated()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('member_id', (string) $target->id);
    }

    public function test_member_cannot_collect_for_another_member(): void
    {
        $self = $this->member([
            'phone' => '01900000003',
            'email' => 'self2@example.com',
            'member_code' => 'M-2003',
            'referral_code' => 'SL-2003',
        ]);
        $target = $this->member([
            'phone' => '01900000004',
            'email' => 'target2@example.com',
            'member_code' => 'M-2004',
            'referral_code' => 'TG-2004',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'member',
            'role_id' => Role::memberId(),
            'phone' => '01800000002',
            'member_id' => $self->id,
        ]));

        $this->postJson('/api/v1/payments', $this->payload($target->id))
            ->assertForbidden()
            ->assertJsonPath('message', 'Members can only submit their own payments.');
    }

    public function test_member_can_submit_own_payment_for_approval(): void
    {
        $self = $this->member([
            'phone' => '01900000005',
            'email' => 'self3@example.com',
            'member_code' => 'M-2005',
            'referral_code' => 'SL-2005',
        ]);

        Sanctum::actingAs(User::factory()->create([
            'role' => 'member',
            'role_id' => Role::memberId(),
            'phone' => '01800000003',
            'member_id' => $self->id,
        ]));

        $this->postJson('/api/v1/payments', $this->payload($self->id))
            ->assertCreated()
            ->assertJsonPath('status', 'pending');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $memberId): array
    {
        return [
            'member_id' => $memberId,
            'amount' => 500,
            'payment_method' => 'hand_cash',
            'allocations' => [[
                'billing_month' => now()->startOfMonth()->toDateString(),
                'amount' => 500,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
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
