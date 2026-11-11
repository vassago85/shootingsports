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
 * Rimfire and air-rifle benchrest bodies named by Louise Taylor
 * (On Target Africa) in November 2026. Copy is taken from the
 * public sites. SAPSSF redacts its FAR and NPC numbers on the
 * about page, so those identifiers are left unset.
 *
 * @see https://www.sapssf.com/?p=AboutUs
 * @see https://www.wrabf.com/
 */
class BenchrestFederationsSeeder extends Seeder
{
    public function run(): void
    {
        $benchrest = Discipline::query()->where('slug', 'benchrest')->firstOrFail();
        $air = Discipline::query()->where('slug', 'benchrest-airgun')->firstOrFail();

        $sapssf = Organisation::query()->updateOrCreate(
            ['slug' => 'sapssf'],
            [
                'name' => 'South African Precision Sport Shooting Federation',
                'short_name' => 'SAPSSF',
                'type' => OrganisationType::Federation,
                'province' => null,
                'town' => null,
                'website_url' => 'https://www.sapssf.com/',
                'description' => 'SAPS accredited national federation for rimfire benchrest and air rifle benchrest, including extreme benchrest. Affiliated to the South African Sport Shooting Confederation. League, provincial and national competitions feed a national ranking, and a national team is selected every second year for a world championship.',
                'accredited' => true,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );
        $sapssf->syncDisciplines([$benchrest->id, $air->id], $benchrest->id);

        $wrabf = Organisation::query()->updateOrCreate(
            ['slug' => 'wrabf'],
            [
                'name' => 'World Rimfire and Air Rifle Benchrest Federation',
                'short_name' => 'WRABF',
                'type' => OrganisationType::Federation,
                'province' => null,
                'town' => null,
                'email' => 'secretary@wrabf.com',
                'website_url' => 'https://www.wrabf.com/',
                'facebook_url' => 'https://www.facebook.com/groups/WRABF/',
                'description' => 'World federation for rimfire and air rifle benchrest. It runs international competitions with national bodies, including world championships and world postal matches. The WRABF site points South Africa 2027 at the South African Precision Sport Shooting Federation.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );
        $wrabf->syncDisciplines([$benchrest->id, $air->id], $benchrest->id);
    }
}
