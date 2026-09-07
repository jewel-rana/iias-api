<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_donations', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('fundraising_event_id')->constrained()->cascadeOnDelete();
            $table->string('donor_type'); // member|non_member
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('donor_name');
            $table->string('donor_phone')->nullable();
            $table->string('donor_email')->nullable();
            $table->foreignId('referred_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->unsignedInteger('amount');
            $table->string('payment_method');
            $table->string('collector_name')->nullable();
            $table->string('transaction_reference')->nullable();
            $table->dateTime('payment_date');
            $table->string('status')->default('confirmed');
            $table->string('idempotency_key')->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_donations');
    }
};
