<?php

namespace Database\Seeders;

use App\Enums\DigestFrequency;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the fixed set of staff accounts so the Filament admin at /admin/login
 * is reachable on a freshly-migrated database (local or production).
 *
 * Each account is created with is_staff = true, which is what
 * User::canAccessPanel() checks. Passwords default to "password" and should be
 * changed on first login — set ADMIN_SEED_PASSWORD in the environment to
 * override the default for every seeded admin at once before running.
 *
 * Run manually with:
 *   php artisan db:seed --class=AdminUserSeeder
 */
class AdminUserSeeder extends Seeder
{
    /**
     * The site's two administrators. Both hold the same access level
     * (is_staff = true → full Filament access).
     *
     * @var list<array{name: string, email: string}>
     */
    private const ADMINS = [
        ['name' => 'Paul Charsley', 'email' => 'paul@charsley.co.za'],
        ['name' => 'Dirk Pio',      'email' => 'dirkpio01@gmail.com'],
    ];

    public function run(): void
    {
        $password = (string) env('ADMIN_SEED_PASSWORD', 'password');

        foreach (self::ADMINS as $admin) {
            $user = User::query()->updateOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'password' => $password, // hashed by the User model cast
                    'is_staff' => true,
                    'digest_frequency' => DigestFrequency::None,
                    'email_verified_at' => now(),
                ],
            );

            $this->command?->info(sprintf(
                'Staff user ready: %s <%s> (id=%d, is_staff=%s)',
                $user->name,
                $user->email,
                $user->id,
                $user->is_staff ? 'true' : 'false',
            ));
        }

        if ($password === 'password') {
            $this->command?->warn('All seeded admins share the default password "password" — change on first login.');
        }
    }
}
