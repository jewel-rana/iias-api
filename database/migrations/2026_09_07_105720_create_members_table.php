<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_code')->unique();
            $table->string('name');
            $table->string('phone')->unique();
            $table->unsignedInteger('monthly_amount')->default(500);
            $table->string('collector_name')->nullable();
            $table->string('status')->default('unpaid'); // paid|partial|unpaid
            $table->unsignedInteger('total_paid')->default(0);
            $table->unsignedInteger('outstanding')->default(0);
            $table->unsignedInteger('advance')->default(0);
            $table->unsignedSmallInteger('paid_months')->default(0);
            $table->unsignedSmallInteger('due_months')->default(0);
            $table->unsignedSmallInteger('advance_months')->default(0);
            $table->string('referral_code')->unique();
            $table->string('status_flag')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
