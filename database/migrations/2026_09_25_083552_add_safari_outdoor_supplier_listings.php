<?php

use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Provider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $services = [
            ProviderCategory::Ammunition->value,
            ProviderCategory::Optics->value,
            ProviderCategory::ReloadingComponents->value,
            ProviderCategory::SafesStorage->value,
        ];

        $shared = [
            'category' => ProviderCategory::Dealer,
            'services' => $services,
            'email' => 'info@so.co.za',
            'website_url' => 'https://safarioutdoor.co.za',
            'tagline' => 'Hunting and firearms shop. Optics, ammunition, reloading, and rifle safes.',
            'status' => ListingStatus::Published,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Staff,
        ];

        $stores = [
            [
                'slug' => 'safari-outdoor-pretoria',
                'name' => 'Safari Outdoor Pretoria',
                'province' => Province::Gauteng,
                'town' => 'Lynnwood',
                'metro' => GautengMetro::Pretoria,
                'phone' => '086 122 2269',
                'description' => 'Hunting and firearms shop at Lynnwood Bridge, Daventry Road and Lynnwood Road, Pretoria. Also stocks outdoor and fishing gear.',
            ],
            [
                'slug' => 'safari-outdoor-johannesburg',
                'name' => 'Safari Outdoor Johannesburg',
                'province' => Province::Gauteng,
                'town' => 'Sunninghill',
                'metro' => GautengMetro::Johannesburg,
                'phone' => '086 114 3545',
                'description' => 'Hunting and firearms shop at 3 Achter Road, Rivonia Crossing, Sunninghill. Also stocks outdoor and fishing gear.',
            ],
            [
                'slug' => 'safari-outdoor-east-rand',
                'name' => 'Safari Outdoor East Rand',
                'province' => Province::Gauteng,
                'town' => 'Boksburg',
                'metro' => null,
                'phone' => '086 100 0071',
                'description' => 'Hunting and firearms shop at 113 North Rand Road, Boksburg. Also stocks outdoor and fishing gear.',
            ],
            [
                'slug' => 'safari-outdoor-west-rand',
                'name' => 'Safari Outdoor West Rand',
                'province' => Province::Gauteng,
                'town' => 'Krugersdorp',
                'metro' => null,
                'phone' => '086 111 4324',
                'description' => 'Hunting and firearms shop at Furrow Road, Cradlestone Mall, Krugersdorp. Also stocks outdoor and fishing gear.',
            ],
            [
                'slug' => 'safari-outdoor-stellenbosch',
                'name' => 'Safari Outdoor Stellenbosch',
                'province' => Province::WesternCape,
                'town' => 'Koelenhof',
                'metro' => null,
                'phone' => '086 111 4330',
                'description' => 'Hunting and firearms shop at the corner of the R304 and Bottelary Road, Devon Place Centre, Koelenhof. Also stocks outdoor and fishing gear.',
            ],
            [
                'slug' => 'safari-outdoor-nelspruit',
                'name' => 'Safari Outdoor Nelspruit',
                'province' => Province::Mpumalanga,
                'town' => 'Mbombela',
                'metro' => null,
                'phone' => '013 813 5500',
                'description' => 'Hunting and firearms shop at the corner of Samora Drive and Du Preez Street, Valley Hyper, Mbombela. Also stocks outdoor and fishing gear.',
            ],
            [
                'slug' => 'safari-outdoor-bloemfontein',
                'name' => 'Safari Outdoor Bloemfontein',
                'province' => Province::FreeState,
                'town' => 'Bloemfontein',
                'metro' => null,
                'phone' => '086 110 0019',
                'description' => 'Hunting and firearms shop at the corner of Abrahamskraal and Dealesville Road, on the R64, Bloemfontein. Also stocks outdoor and fishing gear.',
            ],
        ];

        foreach ($stores as $store) {
            Provider::query()->updateOrCreate(
                ['slug' => $store['slug']],
                array_merge($shared, $store),
            );
        }
    }

    public function down(): void
    {
        Provider::query()
            ->whereIn('slug', [
                'safari-outdoor-pretoria',
                'safari-outdoor-johannesburg',
                'safari-outdoor-east-rand',
                'safari-outdoor-west-rand',
                'safari-outdoor-stellenbosch',
                'safari-outdoor-nelspruit',
                'safari-outdoor-bloemfontein',
            ])
            ->forceDelete();
    }
};
