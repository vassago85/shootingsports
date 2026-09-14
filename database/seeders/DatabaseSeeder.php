<?php

namespace Database\Seeders;

use App\Enums\DigestFrequency;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DisciplineSeeder::class,
            FlagSeeder::class,
            AdSlotSeeder::class,
            // Staff account for the Filament admin (paul@charsley.co.za).
            // Idempotent, runs in every environment so production also gets a
            // first-login account after `php artisan migrate --seed`.
            AdminUserSeeder::class,
            SendItElr2026Seeder::class,
        ]);

        if (! app()->environment('local')) {
            return;
        }

        // Extra convenience account for local dev only.
        User::query()->updateOrCreate(
            ['email' => 'admin@shootingsports.test'],
            [
                'name' => 'Staff',
                'password' => 'password',
                'is_staff' => true,
                'digest_frequency' => DigestFrequency::None,
                'email_verified_at' => now(),
            ],
        );
    }
}
