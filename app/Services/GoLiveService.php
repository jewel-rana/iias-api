<?php

namespace App\Services;

use App\Models\OrganizationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class GoLiveService
{
    public const LOCK_FILE = 'live.lock';

    /**
     * Transactional tables wiped when going live.
     * Catalog tables (expense_heads, committee_roles) and organization_settings are kept.
     *
     * @var list<string>
     */
    private const WIPE_TABLES = [
        'meeting_attendees',
        'meetings',
        'device_tokens',
        'payment_allocations',
        'payments',
        'monthly_dues',
        'event_donations',
        'fundraising_events',
        'expenses',
        'member_join_requests',
        'committee_members',
        'members',
        'personal_access_tokens',
        'sessions',
        'password_reset_tokens',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
    ];

    public static function lockPath(): string
    {
        return storage_path('app/'.self::LOCK_FILE);
    }

    public static function isLive(): bool
    {
        if (is_file(self::lockPath())) {
            return true;
        }

        if (! Schema::hasTable('organization_settings')
            || ! Schema::hasColumn('organization_settings', 'is_live')) {
            return false;
        }

        return (bool) OrganizationSetting::query()->value('is_live');
    }

    public static function assertNotLive(): void
    {
        if (self::isLive()) {
            throw new RuntimeException(
                'Application is already in live mode. The go-live wipe is permanently disabled.'
            );
        }
    }

    /**
     * Wipe demo/test records, keep org settings, expense heads, committee roles, and admin users.
     */
    public function run(): void
    {
        self::assertNotLive();

        Schema::disableForeignKeyConstraints();

        try {
            User::query()->update(['member_id' => null]);

            foreach (self::WIPE_TABLES as $table) {
                $this->emptyTable($table);
            }

            User::query()->where('role', '!=', 'admin')->delete();
            User::query()->update(['member_id' => null]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $settings = OrganizationSetting::current();
        $settings->opening_fund_balance = 0;
        $settings->is_live = true;
        $settings->went_live_at = now();
        $settings->save();

        $this->writeLockFile($settings->went_live_at?->toIso8601String() ?? now()->toIso8601String());
        Cache::flush();
    }

    private function emptyTable(string $table): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->delete();

        try {
            $this->resetAutoIncrement($table);
        } catch (\Throwable) {
            // Skip tables without an auto-increment id (sessions, cache, …).
        }
    }

    private function resetAutoIncrement(string $table): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
        } elseif ($driver === 'pgsql') {
            $quoted = Schema::getConnection()->getTablePrefix().$table;
            DB::statement("ALTER SEQUENCE {$quoted}_id_seq RESTART WITH 1");
        } elseif ($driver === 'sqlite' && Schema::hasTable('sqlite_sequence')) {
            DB::table('sqlite_sequence')->where('name', $table)->delete();
        }
    }

    private function writeLockFile(string $wentLiveAt): void
    {
        File::ensureDirectoryExists(dirname(self::lockPath()));
        File::put(
            self::lockPath(),
            implode("\n", [
                'IIAS live mode',
                "went_live_at={$wentLiveAt}",
                'The go-live wipe command is permanently disabled.',
                '',
            ])
        );
    }
}
