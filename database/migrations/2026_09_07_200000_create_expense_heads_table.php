<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('kind'); // salary, festival_bonus, operational, charity, other
            $table->string('default_recurrence')->default('monthly'); // monthly, occasional
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['kind', 'is_active']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('expense_head_id')
                ->nullable()
                ->after('title')
                ->constrained('expense_heads')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_head_id');
        });
        Schema::dropIfExists('expense_heads');
    }
};
