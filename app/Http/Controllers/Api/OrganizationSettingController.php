<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganizationSetting;
use Illuminate\Http\Request;

class OrganizationSettingController extends Controller
{
    public function show()
    {
        return response()->json($this->transform(OrganizationSetting::current()));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'organization_name' => ['required', 'string', 'max:160'],
            'tagline' => ['nullable', 'string', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'default_monthly_amount' => ['required', 'integer', 'min:1'],
            'currency_symbol' => ['required', 'string', 'max:8'],
            'referral_enabled' => ['required', 'boolean'],
            'public_join_enabled' => ['required', 'boolean'],
            'wallets' => ['sometimes', 'array'],
            'wallets.*.label' => ['nullable', 'string', 'max:40'],
            'wallets.*.number' => ['nullable', 'string', 'max:40'],
        ]);

        $data['wallets'] = array_values(array_filter(
            array_map(function ($row) {
                $number = preg_replace('/\s+/', '', (string) ($row['number'] ?? ''));
                $label = trim((string) ($row['label'] ?? ''));
                if ($number === '') {
                    return null;
                }

                return [
                    'label' => $label,
                    'number' => $number,
                ];
            }, $data['wallets'] ?? OrganizationSetting::current()->wallets ?? []),
        ));

        $settings = OrganizationSetting::current();
        $settings->update($data);

        return response()->json($this->transform($settings->fresh()));
    }

    private function transform(OrganizationSetting $s): array
    {
        return [
            'organization_name' => $s->organization_name,
            'tagline' => $s->tagline,
            'contact_phone' => $s->contact_phone,
            'address' => $s->address,
            'default_monthly_amount' => $s->default_monthly_amount,
            'currency_symbol' => $s->currency_symbol,
            'referral_enabled' => $s->referral_enabled,
            'public_join_enabled' => (bool) $s->public_join_enabled,
            'wallets' => array_values($s->wallets ?? []),
            'is_live' => (bool) $s->is_live,
            'went_live_at' => $s->went_live_at?->toIso8601String(),
        ];
    }
}
