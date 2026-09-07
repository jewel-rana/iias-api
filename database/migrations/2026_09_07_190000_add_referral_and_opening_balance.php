<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_join_requests', function (Blueprint $table) {
            $table->string('referral_code')->nullable()->after('preferred_monthly_amount');
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('opening_fund_balance')->default(0)->after('public_join_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('member_join_requests', function (Blueprint $table) {
            $table->dropColumn('referral_code');
        });

        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn('opening_fund_balance');
        });
    }
};
