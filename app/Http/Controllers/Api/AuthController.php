<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::findByPhone($data['phone']);

        if (! $user) {
            if (Member::findByPhone($data['phone'])) {
                throw ValidationException::withMessages([
                    'password' => ['Password is not set. Use Forgot password to create one.'],
                ]);
            }

            throw ValidationException::withMessages([
                'phone' => ['Invalid phone or password.'],
            ]);
        }

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'phone' => ['Invalid phone or password.'],
            ]);
        }

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->payload($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($this->payload($request->user()));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $memberId = $user->member_id;

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('users', 'phone')->ignore($user->id),
                Rule::unique('members', 'phone')->ignore($memberId),
            ],
            'email' => [
                'sometimes',
                'email',
                'max:120',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('members', 'email')->ignore($memberId),
            ],
            'current_password' => ['required_with:password'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if (! empty($data['password'])) {
            if (! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => ['The current password is incorrect.'],
                ]);
            }
            $user->password = $data['password'];
        }

        $profile = array_intersect_key($data, array_flip(['name', 'phone', 'email']));
        $user->fill($profile);
        $user->save();

        if ($memberId && $profile !== []) {
            Member::query()->where('id', $memberId)->update($profile);
        }

        return response()->json($this->payload($user->fresh()->loadMissing('accessRole')));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function forgotPassword(Request $request, PasswordResetService $resets)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
        ]);

        $result = $resets->requestCode($data['phone']);

        return response()->json(array_filter([
            'message' => 'If this number is registered, a reset code was emailed.',
            'email_hint' => $result['email_hint'],
            'debug_code' => $result['debug_code'],
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function resetPassword(Request $request, PasswordResetService $resets)
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'size:6'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $resets->reset($data['phone'], $data['code'], $data['password']);

        return response()->json([
            'message' => 'Password updated. You can log in now.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user): array
    {
        $user->loadMissing('accessRole');

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'email' => $user->email,
            'role' => $this->appRole($user),
            'role_code' => $user->accessRole?->code ?? $user->role,
            'role_name' => $user->accessRole?->name ?? $user->role,
            'permissions' => $user->permissionKeys(),
            'member_id' => $this->linkedMemberId($user),
        ];
    }

    private function linkedMemberId(User $user): ?string
    {
        if ($user->member_id) {
            return (string) $user->member_id;
        }

        if (! filled($user->phone)) {
            return null;
        }

        $member = Member::findByPhone($user->phone);
        if (! $member) {
            return null;
        }

        $user->member_id = $member->id;
        $user->save();

        return (string) $member->id;
    }

    private function appRole(User $user): string
    {
        if ($user->role === 'admin' || $user->accessRole?->code === 'admin') {
            return 'admin';
        }

        return $user->isStaff() ? 'collector' : 'member';
    }
}
