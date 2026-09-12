<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GoLiveService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class GoLiveCommand extends Command
{
    protected $signature = 'app:go-live
                            {--force : Skip the confirmation prompt}';

    protected $description = 'Wipe demo/test data and permanently switch the application to live mode';

    public function handle(GoLiveService $goLive): int
    {
        if (GoLiveService::isLive()) {
            $this->error('Application is already in live mode. This command will not run again.');

            return self::FAILURE;
        }

        $this->warn('This permanently deletes operational data:');
        $this->line('  members, dues, payments, donations, campaigns, expenses,');
        $this->line('  meetings, join requests, committee people, and non-admin users.');
        $this->newLine();
        $this->info('Kept: organization settings, expense heads, committee roles, admin logins.');
        $this->info('Opening fund balance is reset to 0.');
        $this->newLine();
        $this->warn('After this, the app is flagged live and this command cannot be used again.');

        if (! $this->option('force')) {
            $typed = $this->ask('Type GO LIVE to confirm');
            if ($typed !== 'GO LIVE') {
                $this->error('Aborted.');

                return self::FAILURE;
            }
        }

        try {
            $goLive->run();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Go-live wipe failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $adminCount = User::query()->where('role', 'admin')->count();

        $this->newLine();
        $this->info('Live mode is on. Demo data has been removed.');
        $this->comment('Admin accounts can still log in. Re-login will be required (tokens were cleared).');

        if ($adminCount === 0) {
            $this->warn('No admin user was found. Create one before using the app.');
        } else {
            $this->comment("{$adminCount} admin account(s) kept.");
        }

        return self::SUCCESS;
    }
}
