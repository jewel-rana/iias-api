<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->json('wallets')->nullable()->after('public_join_enabled');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('organization_wallet_label')->nullable()->after('collector_name');
            $table->string('organization_wallet_number')->nullable()->after('organization_wallet_label');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['organization_wallet_label', 'organization_wallet_number']);
        });
        Schema::table('organization_settings', function (Blueprint $table) {
            $table->dropColumn('wallets');
        });
    }
};
