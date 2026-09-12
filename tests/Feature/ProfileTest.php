<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_own_profile_and_linked_member(): void
    {
        $member = $this->member();
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'phone' => '01911111111',
            'password' => 'secret12',
            'role' => 'member',
            'role_id' => Role::memberId(),
            'member_id' => $member->id,
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me', [
            'name' => 'New Name',
            'phone' => '01922222222',
            'email' => 'new@example.com',
        ])->assertOk()
            ->assertJsonPath('name', 'New Name')
            ->assertJsonPath('phone', '01922222222')
            ->assertJsonPath('email', 'new@example.com');

        $this->assertSame('New Name', $member->fresh()->name);
        $this->assertSame('01922222222', $member->fresh()->phone);
        $this->assertSame('new@example.com', $member->fresh()->email);
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'phone' => '01712345678',
            'password' => 'secret12',
            'role' => 'admin',
            'role_id' => Role::query()->where('code', 'admin')->value('id'),
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me', [
            'current_password' => 'secret12',
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpass1', $user->fresh()->password));
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create([
            'phone' => '01712345679',
            'password' => 'secret12',
            'role' => 'admin',
            'role_id' => Role::query()->where('code', 'admin')->value('id'),
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me', [
            'password' => 'newpass1',
            'password_confirmation' => 'newpass1',
        ])->assertStatus(422);
    }

    public function test_admin_can_update_member_profile_fields(): void
    {
        $admin = User::factory()->create([
            'phone' => '01700000001',
            'role' => 'admin',
            'role_id' => Role::query()->where('code', 'admin')->value('id'),
        ]);
        Sanctum::actingAs($admin);
        $member = $this->member();
        $user = User::factory()->create([
            'name' => $member->name,
            'email' => 'linked@example.com',
            'phone' => $member->phone,
            'role' => 'member',
            'role_id' => Role::memberId(),
            'member_id' => $member->id,
        ]);

        $this->putJson('/api/v1/members/'.$member->id, [
            'name' => 'Updated Member',
            'phone' => '01899999999',
            'email' => 'updated@example.com',
        ])->assertOk()
            ->assertJsonPath('name', 'Updated Member')
            ->assertJsonPath('phone', '01899999999')
            ->assertJsonPath('email', 'updated@example.com');

        $this->assertSame('Updated Member', $user->fresh()->name);
        $this->assertSame('01899999999', $user->fresh()->phone);
        $this->assertSame('updated@example.com', $user->fresh()->email);
    }

    public function test_me_links_staff_to_member_with_the_same_phone(): void
    {
        $member = $this->member(['phone' => '01755556666']);
        $admin = User::factory()->create([
            'name' => 'Admin Member',
            'phone' => '01755556666',
            'role' => 'admin',
            'role_id' => Role::query()->where('code', 'admin')->value('id'),
            'member_id' => null,
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('member_id', (string) $member->id);

        $this->assertSame($member->id, $admin->fresh()->member_id);
    }

    private function member(array $overrides = []): Member
    {
        return Member::query()->create(array_merge([
            'member_code' => 'M-001200',
            'name' => 'Profile Member',
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
            'referral_code' => 'PM-1200',
            'role_id' => Role::memberId(),
        ], $overrides));
    }
}
