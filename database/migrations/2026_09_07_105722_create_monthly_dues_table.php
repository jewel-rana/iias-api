<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_dues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->date('billing_month');
            $table->unsignedInteger('amount_due');
            $table->unsignedInteger('amount_paid')->default(0);
            $table->string('status')->default('unpaid'); // unpaid|partial|paid
            $table->timestamps();

            $table->unique(['member_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_dues');
    }
};
