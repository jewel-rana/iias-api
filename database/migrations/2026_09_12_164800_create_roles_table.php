<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('roles')->insert([
            [
                'name' => 'Admin',
                'code' => 'admin',
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 1,
                'permissions' => json_encode(Permissions::admin()),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Collector',
                'code' => 'collector',
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 2,
                'permissions' => json_encode(Permissions::collector()),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Member',
                'code' => 'member',
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 3,
                'permissions' => json_encode(Permissions::member()),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('email')->constrained('roles')->nullOnDelete();
        });

        $ids = DB::table('roles')->pluck('id', 'code');
        foreach (['admin', 'collector', 'member'] as $code) {
            if (! isset($ids[$code])) {
                continue;
            }
            DB::table('users')->where('role', $code)->update(['role_id' => $ids[$code]]);
        }
        if (isset($ids['member'])) {
            DB::table('members')->whereNull('role_id')->update(['role_id' => $ids['member']]);
        }
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::dropIfExists('roles');
    }
};
