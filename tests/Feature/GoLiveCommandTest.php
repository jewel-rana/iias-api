<?php

namespace Tests\Feature;

use App\Models\CommitteeRole;
use App\Models\ExpenseHead;
use App\Models\Member;
use App\Models\OrganizationSetting;
use App\Models\User;
use App\Services\GoLiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GoLiveCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        @unlink(GoLiveService::lockPath());
        parent::tearDown();
    }

    public function test_go_live_wipes_data_keeps_admin_and_catalogs_then_locks(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@ummah.local',
            'phone' => '01712345678',
            'password' => Hash::make('admin'),
            'role' => 'admin',
        ]);

        User::factory()->create([
            'name' => 'Member User',
            'email' => 'member@ummah.local',
            'phone' => '01811112222',
            'role' => 'member',
        ]);

        OrganizationSetting::query()->create([
            'organization_name' => 'IIAS',
            'opening_fund_balance' => 562500,
        ]);

        ExpenseHead::query()->create([
            'name' => 'Utilities',
            'code' => 'utilities',
            'kind' => 'operational',
            'default_recurrence' => 'monthly',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        CommitteeRole::query()->create([
            'name' => 'Chairman',
            'code' => 'chairman',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Member::query()->create([
            'member_code' => 'M-001024',
            'name' => 'Abdul Karim',
            'phone' => '01811112222',
            'monthly_amount' => 500,
            'status' => 'unpaid',
            'referral_code' => 'AK-1024',
        ]);

        $this->artisan('app:go-live', ['--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('members', 0);
        $this->assertDatabaseMissing('users', ['email' => 'member@ummah.local']);
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
        $this->assertDatabaseHas('expense_heads', ['code' => 'utilities']);
        $this->assertDatabaseHas('committee_roles', ['code' => 'chairman']);

        $settings = OrganizationSetting::query()->first();
        $this->assertTrue((bool) $settings?->is_live);
        $this->assertSame(0, (int) $settings?->opening_fund_balance);
        $this->assertNotNull($settings?->went_live_at);
        $this->assertFileExists(GoLiveService::lockPath());
        $this->assertTrue(GoLiveService::isLive());

        $this->artisan('app:go-live', ['--force' => true])
            ->assertFailed();

        $this->expectException(\RuntimeException::class);
        $this->seed(\Database\Seeders\DemoSeeder::class);
    }
}
