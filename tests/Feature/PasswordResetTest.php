<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\Member;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_does_not_reveal_whether_phone_exists(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'phone' => '01900000000',
        ])->assertOk()
            ->assertJson([
                'message' => 'If this number is registered, a reset code was emailed.',
            ])
            ->assertJsonMissingPath('debug_code')
            ->assertJsonMissingPath('email_hint');

        Mail::assertNothingSent();
    }

    public function test_registered_phone_receives_a_reset_code_email(): void
    {
        Mail::fake();
        $this->user();

        $this->postJson('/api/v1/auth/forgot-password', [
            'phone' => '01712345678',
        ])->assertOk()->assertJsonPath('email_hint', 'a***@ummah.local');

        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) {
            return $mail->hasTo('admin@ummah.local') && preg_match('/^\d{6}$/', $mail->code) === 1;
        });
    }

    public function test_local_environment_uses_the_default_otp(): void
    {
        Mail::fake();
        $this->user();
        $this->app['env'] = 'local';

        $this->postJson('/api/v1/auth/forgot-password', [
            'phone' => '01712345678',
        ])->assertOk();

        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) {
            return $mail->code === PasswordResetService::DEFAULT_CODE;
        });
    }

    public function test_non_local_environment_uses_a_random_otp(): void
    {
        Mail::fake();
        $this->user();
        $this->assertFalse($this->app->isLocal());

        $this->postJson('/api/v1/auth/forgot-password', [
            'phone' => '01712345678',
        ])->assertOk();

        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) {
            return preg_match('/^\d{6}$/', $mail->code) === 1;
        });
    }

    public function test_member_without_login_is_told_to_set_a_password(): void
    {
        $this->member();

        $this->postJson('/api/v1/auth/login', [
            'phone' => '01911785317',
            'password' => 'admin',
        ])->assertStatus(422)->assertJsonPath(
            'errors.password.0',
            'Password is not set. Use Forgot password to create one.'
        );
    }

    public function test_member_without_email_cannot_receive_a_reset_code(): void
    {
        Mail::fake();
        $this->member(['email' => null]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'phone' => '01911785317',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        Mail::assertNothingSent();
    }

    public function test_member_without_login_can_set_password_via_reset(): void
    {
        Mail::fake();
        $member = $this->member();

        $this->postJson('/api/v1/auth/forgot-password', [
            'phone' => '01911785317',
        ])->assertOk();

        $code = $this->mailedCode('member@example.com');

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => '01911785317',
            'code' => $code,
            'password' => 'secret1',
            'password_confirmation' => 'secret1',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'phone' => '01911785317',
            'password' => 'secret1',
        ])->assertOk()
            ->assertJsonPath('user.role', 'member')
            ->assertJsonPath('user.member_id', (string) $member->id);
    }

    public function test_reset_password_updates_password_and_revokes_tokens(): void
    {
        Mail::fake();
        $user = $this->user();
        $token = $user->createToken('mobile')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $this->postJson('/api/v1/auth/forgot-password', [
            'phone' => '01712345678',
        ])->assertOk();

        $code = $this->mailedCode();

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => '01712345678',
            'code' => $code,
            'password' => 'newpass',
            'password_confirmation' => 'newpass',
        ])->assertOk();

        $user->refresh();
        $this->assertTrue(Hash::check('newpass', $user->password));
        $this->assertSame(0, PersonalAccessToken::query()->where('tokenable_id', $user->id)->count());

        $this->postJson('/api/v1/auth/login', [
            'phone' => '01712345678',
            'password' => 'admin',
        ])->assertStatus(422);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '01712345678',
            'password' => 'newpass',
        ])->assertOk()->assertJsonPath('user.phone', '01712345678');
    }

    public function test_reset_password_rejects_an_invalid_code(): void
    {
        Mail::fake();
        $this->user();

        $this->postJson('/api/v1/auth/forgot-password', [
            'phone' => '01712345678',
        ])->assertOk();

        $this->postJson('/api/v1/auth/reset-password', [
            'phone' => '01712345678',
            'code' => '000000',
            'password' => 'newpass',
            'password_confirmation' => 'newpass',
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    private function mailedCode(?string $to = null): string
    {
        $code = null;
        Mail::assertSent(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use ($to, &$code) {
            if ($to !== null && ! $mail->hasTo($to)) {
                return false;
            }
            $code = $mail->code;

            return true;
        });
        $this->assertNotEmpty($code);

        return $code;
    }

    private function user(): User
    {
        return User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@ummah.local',
            'phone' => '01712345678',
            'password' => 'admin',
            'role' => 'admin',
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
