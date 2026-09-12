<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'max:20'],
            'device_id' => ['nullable', 'string', 'max:120'],
        ]);

        $device = DeviceToken::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'] ?? null,
                'device_id' => $data['device_id'] ?? null,
            ],
        );

        return response()->json([
            'id' => (string) $device->id,
        ], 201);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('token', $data['token'])
            ->delete();

        return response()->json(['message' => 'Unregistered']);
    }
}
