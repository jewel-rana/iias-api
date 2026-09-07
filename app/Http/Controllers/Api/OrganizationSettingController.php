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
        ]);

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
            'public_join_enabled' => $s->public_join_enabled,
        ];
    }
}
