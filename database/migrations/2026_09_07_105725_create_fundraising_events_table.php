<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fundraising_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('goal_amount');
            $table->unsignedInteger('raised_amount')->default(0);
            $table->unsignedInteger('donor_count')->default(0);
            $table->string('cover_path')->nullable();
            $table->string('venue')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('status')->default('draft'); // draft|active|paused|closed|archived
            $table->boolean('accept_members')->default(true);
            $table->boolean('accept_non_members')->default(true);
            $table->boolean('require_referral_for_guests')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fundraising_events');
    }
};
