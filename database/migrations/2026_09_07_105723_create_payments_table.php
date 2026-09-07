<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('payment_method'); // mobile_wallet|cash_to_collector|hand_cash
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
        Schema::dropIfExists('payments');
    }
};
