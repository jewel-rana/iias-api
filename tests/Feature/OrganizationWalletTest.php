<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\OrganizationSetting;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganizationWalletTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_can_store_organization_wallets(): void
    {
        Sanctum::actingAs($this->admin());
        $current = OrganizationSetting::current();

        $this->putJson('/api/v1/organization-settings', [
            'organization_name' => $current->organization_name,
            'tagline' => $current->tagline,
            'contact_phone' => $current->contact_phone,
            'address' => $current->address,
            'default_monthly_amount' => $current->default_monthly_amount,
            'currency_symbol' => $current->currency_symbol,
            'referral_enabled' => true,
            'public_join_enabled' => true,
            'wallets' => [
                ['label' => 'bKash', 'number' => '01711 111111'],
                ['label' => 'Nagad', 'number' => '01822222222'],
                ['label' => 'Skip', 'number' => ''],
            ],
        ])->assertOk()
            ->assertJsonPath('wallets.0.label', 'bKash')
            ->assertJsonPath('wallets.0.number', '01711111111')
            ->assertJsonPath('wallets.1.number', '01822222222')
            ->assertJsonCount(2, 'wallets');
    }

    public function test_wallet_payment_requires_org_to_customer_from_and_txn(): void
    {
        Sanctum::actingAs($this->admin());
        OrganizationSetting::current()->update([
            'wallets' => [
                ['label' => 'bKash', 'number' => '01711111111'],
            ],
        ]);
        $member = $this->member();

        $this->postJson('/api/v1/payments', [
            'member_id' => $member->id,
            'amount' => 500,
            'payment_method' => 'mobile_wallet',
            'wallet_account' => '01900000000',
            'transaction_reference' => 'TXN1',
            'allocations' => [[
                'billing_month' => now()->startOfMonth()->toDateString(),
                'amount' => 500,
            ]],
        ])->assertStatus(422);

        $this->postJson('/api/v1/payments', [
            'member_id' => $member->id,
            'amount' => 500,
            'payment_method' => 'mobile_wallet',
            'organization_wallet_number' => '01711111111',
            'organization_wallet_label' => 'bKash',
            'wallet_account' => '01900000000',
            'transaction_reference' => 'TXN99',
            'allocations' => [[
                'billing_month' => now()->startOfMonth()->toDateString(),
                'amount' => 500,
            ]],
        ])->assertCreated()
            ->assertJsonPath('organization_wallet_label', 'bKash')
            ->assertJsonPath('organization_wallet_number', '01711111111')
            ->assertJsonPath('wallet_account', '01900000000')
            ->assertJsonPath('transaction_reference', 'TXN99');
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function member(array $overrides = []): Member
    {
        return Member::query()->create(array_merge([
            'member_code' => 'M-001301',
            'name' => 'Wallet Member',
            'phone' => '01900001111',
            'email' => 'wallet@example.com',
            'monthly_amount' => 500,
            'collector_name' => 'Admin',
            'status' => 'unpaid',
            'total_paid' => 0,
            'outstanding' => 500,
            'advance' => 0,
            'paid_months' => 0,
            'due_months' => 1,
            'advance_months' => 0,
            'referral_code' => 'WM-1301',
            'role_id' => Role::memberId(),
        ], $overrides));
    }
}
