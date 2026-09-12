<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_roles_are_seeded(): void
    {
        $this->assertDatabaseHas('roles', ['code' => 'admin', 'is_system' => true]);
        $this->assertDatabaseHas('roles', ['code' => 'collector', 'is_system' => true]);
        $this->assertDatabaseHas('roles', ['code' => 'member', 'is_system' => true]);
    }

    public function test_admin_can_create_and_update_a_role_with_permissions(): void
    {
        Sanctum::actingAs($this->admin());

        $create = $this->postJson('/api/v1/roles', [
            'name' => 'Treasurer',
            'permissions' => [Permissions::EXPENSES_VIEW, Permissions::REPORTS_VIEW],
        ])->assertCreated()->json('id');

        $this->assertNotEmpty($create);

        $this->putJson('/api/v1/roles/'.$create, [
            'name' => 'Treasurer',
            'is_active' => true,
            'permissions' => [Permissions::EXPENSES_VIEW, Permissions::EXPENSES_CREATE, Permissions::REPORTS_VIEW],
        ])->assertOk()->assertJsonPath('permissions.1', Permissions::EXPENSES_CREATE);
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        Sanctum::actingAs($this->admin());
        $adminRole = Role::query()->where('code', 'admin')->firstOrFail();

        $this->deleteJson('/api/v1/roles/'.$adminRole->id)
            ->assertStatus(422);
    }

    public function test_member_cannot_manage_roles(): void
    {
        $member = User::factory()->create([
            'role' => 'member',
            'role_id' => Role::memberId(),
            'phone' => '01911112222',
        ]);
        Sanctum::actingAs($member);

        $this->postJson('/api/v1/roles', [
            'name' => 'Hacker',
        ])->assertForbidden();
    }

    public function test_login_returns_permissions(): void
    {
        $this->admin();

        $this->postJson('/api/v1/auth/login', [
            'phone' => '01712345678',
            'password' => 'admin',
        ])->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.role_name', 'Admin');
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
}
