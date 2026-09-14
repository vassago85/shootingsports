<?php

namespace Database\Seeders;

use App\Enums\DigestFrequency;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a single, idempotent staff account so the Filament admin at /admin/login
 * is reachable on a freshly-migrated database (local or production).
 *
 * The account is created with is_staff = true, which is what
 * User::canAccessPanel() checks. Password defaults to "password" and should be
 * changed on first login — set ADMIN_SEED_PASSWORD in the environment to
 * override it before seeding on a real server.
 *
 * Run manually with:
 *   php artisan db:seed --class=AdminUserSeeder
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email    = 'paul@charsley.co.za';
        $password = (string) env('ADMIN_SEED_PASSWORD', 'password');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name'              => 'Paul Charsley',
                'password'          => $password, // hashed by the User model cast
                'is_staff'          => true,
                'digest_frequency'  => DigestFrequency::None,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info(sprintf(
            'Staff user ready: %s (id=%d, is_staff=%s)',
            $user->email,
            $user->id,
            $user->is_staff ? 'true' : 'false',
        ));

        if ($password === 'password') {
            $this->command?->warn('Password is the default "password" — log in and change it immediately.');
        }
    }
}
