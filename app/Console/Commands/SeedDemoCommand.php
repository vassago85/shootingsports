<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class SeedDemoCommand extends Command
{
    protected $signature = 'shootingsports:seed-demo';

    protected $description = 'Seed local demo organisations, venues and events (planned and confirmed).';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Demo data is local-only.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class]);

        return self::SUCCESS;
    }
}
