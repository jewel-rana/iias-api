<?php

namespace Database\Seeders;

use App\Services\GoLiveService;
use Illuminate\Database\Seeder;
use RuntimeException;

class GoLiveSeeder extends Seeder
{
    public function run(GoLiveService $goLive): void
    {
        if (GoLiveService::isLive()) {
            $this->command?->error('Application is already in live mode. Go-live wipe is permanently disabled.');

            throw new RuntimeException(
                'Application is already in live mode. The go-live wipe is permanently disabled.'
            );
        }

        $goLive->run();
        $this->command?->info('Live mode is on. Demo data has been removed.');
    }
}
