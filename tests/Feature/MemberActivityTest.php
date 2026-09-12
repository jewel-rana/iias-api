<?php

namespace Tests\Feature;

use App\Models\FundraisingEvent;
use App\Models\Member;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MemberActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_payments_can_be_filtered_by_member(): void
    {
        Sanctum::actingAs($this->admin());
        $member = $this->member();
        $other = $this->member([
            'member_code' => 'M-001100',
            'phone' => '01900000001',
            'email' => 'other@example.com',
            'referral_code' => 'OT-1100',
        ]);

        Payment::query()->create([
            'receipt_number' => 'P-1',
            'member_id' => $member->id,
            'amount' => 500,
            'payment_method' => 'cash_to_collector',
            'payment_date' => now(),
            'status' => 'confirmed',
            'idempotency_key' => 'pay-1',
        ]);
        Payment::query()->create([
            'receipt_number' => 'P-2',
            'member_id' => $other->id,
            'amount' => 700,
            'payment_method' => 'cash_to_collector',
            'payment_date' => now(),
            'status' => 'confirmed',
            'idempotency_key' => 'pay-2',
        ]);

        $this->getJson('/api/v1/payments?member_id='.$member->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.receipt_number', 'P-1');
    }

    public function test_payments_can_be_filtered_by_billing_month(): void
    {
        Sanctum::actingAs($this->admin());
        $member = $this->member();
        $august = Payment::query()->create([
            'receipt_number' => 'P-aug',
            'member_id' => $member->id,
            'amount' => 500,
            'payment_method' => 'mobile_wallet',
            'payment_date' => '2026-08-10',
            'status' => 'confirmed',
            'idempotency_key' => 'pay-aug',
        ]);
        $september = Payment::query()->create([
            'receipt_number' => 'P-sep',
            'member_id' => $member->id,
            'amount' => 500,
            'payment_method' => 'cash_to_collector',
            'payment_date' => '2026-09-10',
            'status' => 'confirmed',
            'idempotency_key' => 'pay-sep',
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $august->id,
            'member_id' => $member->id,
            'billing_month' => '2026-08-01',
            'amount' => 500,
            'status' => 'applied',
        ]);
        PaymentAllocation::query()->create([
            'payment_id' => $september->id,
            'member_id' => $member->id,
            'billing_month' => '2026-09-01',
            'amount' => 500,
            'status' => 'applied',
        ]);

        $this->getJson('/api/v1/payments?billing_month=2026-09')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.receipt_number', 'P-sep')
            ->assertJsonPath('data.0.payment_method', 'cash_to_collector');

        $this->getJson('/api/v1/payments/'.$september->id)
            ->assertOk()
            ->assertJsonPath('receipt_number', 'P-sep')
            ->assertJsonPath('member_name', 'Test Member')
            ->assertJsonPath('allocations.0.billing_month', '2026-09-01');
    }

    public function test_member_donation_is_listed_for_that_member(): void
    {
        Sanctum::actingAs($this->admin());
        $member = $this->member();
        $event = FundraisingEvent::query()->create([
            'title' => 'Flood Relief',
            'slug' => 'flood-relief',
            'goal_amount' => 100000,
            'raised_amount' => 0,
            'donor_count' => 0,
            'starts_at' => now(),
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/events/'.$event->id.'/donations', [
            'donor_type' => 'member',
            'donor_name' => $member->name,
            'donor_phone' => $member->phone,
            'member_id' => $member->id,
            'amount' => 1000,
            'payment_method' => 'mobile_wallet',
        ])->assertCreated()->assertJsonPath('member_id', (string) $member->id);

        $this->getJson('/api/v1/donations?member_id='.$member->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.donor_name', $member->name)
            ->assertJsonPath('data.0.amount', 1000);
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
        ], $overrides));
    }
}
