<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function __construct(private FcmService $fcm) {}

    /**
     * @param  array<string, string>  $data
     */
    public function notifyAll(string $title, string $body, array $data = []): void
    {
        $this->notifyUsers(
            User::query()->pluck('id')->all(),
            $title,
            $body,
            $data,
        );
    }

    /**
     * @param  array<string, string>  $data
     */
    public function notifyStaff(string $title, string $body, array $data = []): void
    {
        $this->notifyUsers(
            User::query()->whereIn('role', ['admin', 'collector'])->pluck('id')->all(),
            $title,
            $body,
            $data,
        );
    }

    /**
     * @param  array<string, string>  $data
     */
    public function notifyMember(int $memberId, string $title, string $body, array $data = []): void
    {
        $this->notifyUsers(
            User::query()->where('member_id', $memberId)->pluck('id')->all(),
            $title,
            $body,
            $data,
        );
    }

    /**
     * @param  array<int, int|string>  $userIds
     * @param  array<string, string>  $data
     */
    public function notifyUsers(array $userIds, string $title, string $body, array $data = []): void
    {
        try {
            if ($userIds === [] || ! $this->fcm->isConfigured()) {
                return;
            }

            $tokens = DeviceToken::query()
                ->whereIn('user_id', $userIds)
                ->get();

            foreach ($tokens as $device) {
                $ok = $this->fcm->sendToToken($device->token, $title, $body, $data);
                if ($ok === false) {
                    $device->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Push notification failed', ['message' => $e->getMessage()]);
        }
    }
}
