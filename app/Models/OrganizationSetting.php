<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrganizationSetting extends Model
{
    protected $fillable = [
        'organization_name',
        'tagline',
        'contact_phone',
        'address',
        'default_monthly_amount',
        'currency_symbol',
        'referral_enabled',
        'public_join_enabled',
        'opening_fund_balance',
    ];

    protected function casts(): array
    {
        return [
            'default_monthly_amount' => 'integer',
            'opening_fund_balance' => 'integer',
            'referral_enabled' => 'bool',
            'public_join_enabled' => 'bool',
        ];
    }

    public static function current(): self
    {
        return static::query()->first() ?? static::query()->create([
            'organization_name' => 'IIAS',
            'tagline' => 'Isapura Islamic United Organization',
            'contact_phone' => '01911785317',
            'address' => 'Isapura Molla Bari Jame Masjid, Bakergonj, Barisal',
            'default_monthly_amount' => 500,
            'currency_symbol' => '৳',
            'referral_enabled' => true,
            'public_join_enabled' => true,
            'opening_fund_balance' => 0,
        ]);
    }
}
