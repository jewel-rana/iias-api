<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
            'user' => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'role' => $user->role,
                'member_id' => $user->member_id ? (string) $user->member_id : null,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id' => (string) $user->id,
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->role,
            'member_id' => $user->member_id ? (string) $user->member_id : null,
        ]);
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

        $debugCode = $resets->requestCode($data['phone']);

        return response()->json(array_filter([
            'message' => 'If this number is registered, a reset code was sent.',
            'debug_code' => $debugCode,
        ]));
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
}
