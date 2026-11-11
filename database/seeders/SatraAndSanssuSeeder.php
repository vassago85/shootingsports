<?php

namespace Database\Seeders;

use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Organisation;
use Illuminate\Database\Seeder;

/**
 * Associations Louise Taylor named in November 2026 as missing from
 * the register. Copy is taken from the public sites. SATRA's site
 * does not publish a phone. No match dates were supplied.
 *
 * @see https://www.satra.org.za/
 * @see https://sanssu.co.za/
 */
class SatraAndSanssuSeeder extends Seeder
{
    public function run(): void
    {
        $target = Discipline::query()->where('slug', 'target-rifle')->firstOrFail();
        $air = Discipline::query()->where('slug', '10m-air-rifle')->firstOrFail();
        $fClass = Discipline::query()->where('slug', 'f-class')->firstOrFail();
        $hunting = Discipline::query()->where('slug', 'hunting-rifle')->firstOrFail();

        $satra = Organisation::query()->updateOrCreate(
            ['slug' => 'satra'],
            [
                'name' => 'South African Target Rifle Association',
                'short_name' => 'SATRA',
                'type' => OrganisationType::Association,
                'province' => null,
                'town' => null,
                'email' => 'satra.22skiet@gmail.com',
                'website_url' => 'https://www.satra.org.za/',
                'facebook_url' => 'https://www.facebook.com/profile.php?id=100073739451678',
                'description' => 'National association for target rifle: smallbore .22 to 100 m, 300 m fullbore, and 10 m air rifle, plus PCP. Classes include peep-sight groups, FX and F-class with telescopes, and an H-class for hunting rifles.',
                'accredited' => false,
                'visitors_welcome' => null,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );
        $satra->syncDisciplines(
            [$target->id, $air->id, $fClass->id, $hunting->id],
            $target->id,
        );

        $sanssu = Organisation::query()->updateOrCreate(
            ['slug' => 'sanssu'],
            [
                'name' => 'South African National School Shooter Union',
                'short_name' => 'SANSSU',
                'type' => OrganisationType::Association,
                'province' => null,
                'town' => null,
                'email' => 'sekretaresse@sanssu.co.za',
                'phone' => '082 959 7105',
                'website_url' => 'https://sanssu.co.za/',
                'facebook_url' => 'https://www.facebook.com/SANSSU-571636082867416',
                'description' => 'National school shooting union. Shooters from the provincial unions compete at 10 m in air rifle, precision, spring-air and air pistol.',
                'accredited' => false,
                'visitors_welcome' => null,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );
        $sanssu->syncDisciplines([$air->id], $air->id);
    }
}
