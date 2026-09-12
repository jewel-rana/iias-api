<?php

namespace Database\Seeders;

use App\Services\GoLiveService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (GoLiveService::isLive()) {
            $this->command?->error('Application is in live mode. Demo seeding is disabled.');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
