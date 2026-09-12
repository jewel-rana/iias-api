<?php

namespace App\Services;

use App\Models\Member;
use App\Models\User;

class MemberAccountService
{
    public function createLogin(Member $member, string $password): User
    {
        $phone = preg_replace('/\D+/', '', $member->phone) ?: $member->phone;
        $user = User::findByPhone($member->phone) ?? User::findByPhone($phone);
        $email = $this->emailFor($member, $user);

        if ($user) {
            $user->fill([
                'name' => $member->name,
                'phone' => $user->phone ?: $phone,
                'email' => $user->email ?: $email,
                'password' => $password,
                'member_id' => $member->id,
            ]);
            if (! in_array($user->role, ['admin', 'collector'], true)) {
                $user->role = 'member';
            }
            $user->save();
            $user->tokens()->delete();

            return $user;
        }

        return User::query()->create([
            'name' => $member->name,
            'phone' => $phone,
            'email' => $email,
            'password' => $password,
            'role' => 'member',
            'member_id' => $member->id,
        ]);
    }

    private function emailFor(Member $member, ?User $user): string
    {
        if (filled($user?->email)) {
            return $user->email;
        }

        if (filled($member->email) && ! $this->emailTaken($member->email, $user)) {
            return $member->email;
        }

        $phone = preg_replace('/\D+/', '', $member->phone) ?: $member->phone;
        $fallback = $phone.'@ummah.local';
        if (! $this->emailTaken($fallback, $user)) {
            return $fallback;
        }

        return $phone.'.'.$member->id.'@ummah.local';
    }

    private function emailTaken(string $email, ?User $ignore): bool
    {
        return User::query()
            ->where('email', $email)
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->exists();
    }
}
