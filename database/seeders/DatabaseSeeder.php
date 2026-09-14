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
        ]);

        if (! app()->environment('local')) {
            return;
        }

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
