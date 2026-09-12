<?php

namespace App\Services;

use App\Mail\PasswordResetCodeMail;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public const TTL_MINUTES = 15;

    public const MAX_ATTEMPTS = 5;

    public function __construct(
        private PushNotificationService $push,
        private MemberAccountService $accounts,
    ) {}

    public function requestCode(string $phone): ?string
    {
        $user = User::findByPhone($phone);
        $member = Member::findByPhone($phone);
        if (! $user && ! $member) {
            return null;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->cacheKey($phone), [
            'hash' => Hash::make($code),
            'attempts' => 0,
            'user_id' => $user?->id,
            'member_id' => $member?->id ?? $user?->member_id,
        ], now()->addMinutes(self::TTL_MINUTES));

        $email = $user?->email ?: $member?->email;
        if (filled($email)) {
            try {
                Mail::to($email)->send(new PasswordResetCodeMail($code));
            } catch (\Throwable $e) {
                Log::warning('Password reset email failed', [
                    'user_id' => $user?->id,
                    'member_id' => $member?->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($user) {
            $this->push->notifyUsers(
                [$user->id],
                'Password reset code',
                "Your code is {$code}. It expires in 15 minutes.",
            );
        }

        Log::info('Password reset code generated', [
            'user_id' => $user?->id,
            'member_id' => $member?->id,
        ]);

        $mailer = (string) config('mail.default');
        if (config('app.debug') && in_array($mailer, ['log', 'array'], true)) {
            return $code;
        }

        return null;
    }

    public function reset(string $phone, string $code, string $password): void
    {
        $user = User::findByPhone($phone);
        $member = Member::findByPhone($phone);
        $key = $this->cacheKey($phone);
        $payload = Cache::get($key);

        if (! is_array($payload) || (! $user && ! $member)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired reset code.'],
            ]);
        }

        $cachedUserId = (int) ($payload['user_id'] ?? 0);
        $cachedMemberId = (int) ($payload['member_id'] ?? 0);

        if (
            ($cachedUserId && $user && $cachedUserId !== (int) $user->id)
            || ($cachedMemberId && $member && $cachedMemberId !== (int) $member->id)
            || ($cachedUserId === 0 && $cachedMemberId === 0)
        ) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired reset code.'],
            ]);
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
            throw ValidationException::withMessages([
                'code' => ['Too many attempts. Request a new code.'],
            ]);
        }

        if (! Hash::check($code, $payload['hash'] ?? '')) {
            $payload['attempts'] = $attempts + 1;
            Cache::put($key, $payload, now()->addMinutes(self::TTL_MINUTES));
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired reset code.'],
            ]);
        }

        if ($user) {
            $user->password = $password;
            if ($member && ! $user->member_id) {
                $user->member_id = $member->id;
            }
            $user->save();
            $user->tokens()->delete();
        } elseif ($member) {
            $this->accounts->createLogin($member, $password);
        }

        Cache::forget($key);
    }

    private function cacheKey(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: $phone;

        return 'password_reset:'.$digits;
    }
}
