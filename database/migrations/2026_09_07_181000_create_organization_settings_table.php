<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table) {
            $table->id();
            $table->string('organization_name');
            $table->string('tagline')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('address')->nullable();
            $table->unsignedInteger('default_monthly_amount')->default(500);
            $table->string('currency_symbol', 8)->default('৳');
            $table->boolean('referral_enabled')->default(true);
            $table->boolean('public_join_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_settings');
    }
};
