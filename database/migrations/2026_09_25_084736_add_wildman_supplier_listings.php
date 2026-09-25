<?php

use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Provider;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $logoPath = 'provider-logos/wildman.png';
        $source = database_path('seeders/data/wildman-logo.png');

        if (is_file($source)) {
            Storage::disk('media')->put($logoPath, (string) file_get_contents($source));
        }

        $services = [
            ProviderCategory::Ammunition->value,
            ProviderCategory::Optics->value,
            ProviderCategory::ReloadingComponents->value,
            ProviderCategory::SafesStorage->value,
        ];

        $shared = [
            'category' => ProviderCategory::Dealer,
            'services' => $services,
            'email' => null,
            'website_url' => 'https://wildmanhuntingandoutdoor.com',
            'logo_path' => $logoPath,
            'tagline' => 'Hunting and firearms shop. Optics, ammunition, reloading, and gun safes.',
            'status' => ListingStatus::Published,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Staff,
        ];

        foreach ($this->stores() as $store) {
            $withGunsmith = $store['gunsmith'] ?? false;
            unset($store['gunsmith']);

            if ($withGunsmith) {
                $store['services'] = array_merge($services, [ProviderCategory::Gunsmith->value]);
            }

            Provider::query()->updateOrCreate(
                ['slug' => $store['slug']],
                array_merge($shared, $store),
            );
        }
    }

    public function down(): void
    {
        Provider::query()
            ->whereIn('slug', array_column($this->stores(), 'slug'))
            ->forceDelete();

        Storage::disk('media')->delete('provider-logos/wildman.png');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stores(): array
    {
        return [
            [
                'slug' => 'wildman-bethlehem',
                'name' => 'Wildman Bethlehem',
                'province' => Province::FreeState,
                'town' => 'Bethlehem',
                'metro' => null,
                'phone' => '058 303 0510',
                'email' => 'orders@wildmanbeth.co.za',
                'description' => 'Hunting and firearms shop on the second floor of the VKB Building, corner of Lindley and Pretorius Street, Bethlehem.',
            ],
            [
                'slug' => 'wildman-brits',
                'name' => 'Wildman Brits',
                'province' => Province::NorthWest,
                'town' => 'Brits',
                'metro' => null,
                'phone' => '062 073 0796',
                'description' => 'Hunting and firearms shop at Stokkiesdraai Business Centre, 1 Rutgers Street, Brits.',
            ],
            [
                'slug' => 'wildman-cape-gate',
                'name' => 'Wildman Cape Gate',
                'province' => Province::WesternCape,
                'town' => 'Brackenfell',
                'metro' => null,
                'phone' => '021 981 8180',
                'description' => 'Hunting and firearms shop at Shop LL01A, Cape Gate Lifestyle Centre, Brackenfell.',
            ],
            [
                'slug' => 'wildman-centurion',
                'name' => 'Wildman Centurion',
                'province' => Province::Gauteng,
                'town' => 'Centurion',
                'metro' => GautengMetro::Pretoria,
                'phone' => '082 925 2749',
                'gunsmith' => true,
                'description' => 'Hunting and firearms shop at Centurion Gate, Building C, corner of John Vorster and Akkerboom Street, Zwartkop. Indoor range and an on-site gunsmith.',
            ],
            [
                'slug' => 'wildman-lephalale',
                'name' => 'Wildman Ellisras',
                'province' => Province::Limpopo,
                'town' => 'Lephalale',
                'metro' => null,
                'phone' => '082 307 2940',
                'description' => 'Hunting and firearms shop at 1 Booysen Street, Lephalale.',
            ],
            [
                'slug' => 'wildman-ermelo',
                'name' => 'Wildman Ermelo',
                'province' => Province::Mpumalanga,
                'town' => 'Ermelo',
                'metro' => null,
                'phone' => '083 560 3654',
                'description' => 'Hunting and firearms shop at 59 Kerk Street, Ermelo.',
            ],
            [
                'slug' => 'wildman-george',
                'name' => 'Wildman George',
                'province' => Province::WesternCape,
                'town' => 'George',
                'metro' => null,
                'phone' => '064 802 7537',
                'description' => 'Hunting and firearms shop at 38 Albert Street, George.',
            ],
            [
                'slug' => 'wildman-douglas',
                'name' => 'Wildman GWK Douglas',
                'province' => Province::NorthernCape,
                'town' => 'Douglas',
                'metro' => null,
                'phone' => '053 298 8450',
                'description' => 'Hunting and firearms shop at 1 De Villiers Street, Douglas.',
            ],
            [
                'slug' => 'wildman-hoedspruit',
                'name' => 'Wildman Hoedspruit',
                'province' => Province::Limpopo,
                'town' => 'Hoedspruit',
                'metro' => null,
                'phone' => '071 882 9895',
                'description' => 'Hunting and firearms shop at Junction, Riverside Park, Hoedspruit.',
            ],
            [
                'slug' => 'wildman-kimberley',
                'name' => 'Wildman Kimberley',
                'province' => Province::NorthernCape,
                'town' => 'Kimberley',
                'metro' => null,
                'phone' => '071 366 1043',
                'description' => 'Hunting and firearms shop at 12 Fabricia Road, Kimberley.',
            ],
            [
                'slug' => 'wildman-middelburg',
                'name' => 'Wildman Middelburg',
                'province' => Province::Mpumalanga,
                'town' => 'Middelburg',
                'metro' => null,
                'phone' => '078 212 1058',
                'description' => 'Hunting and firearms shop at 11 July Street, Middelburg.',
            ],
            [
                'slug' => 'wildman-montana',
                'name' => 'Wildman Montana',
                'province' => Province::Gauteng,
                'town' => 'Montana Park',
                'metro' => GautengMetro::Pretoria,
                'phone' => '072 635 5512',
                'description' => 'Hunting and firearms shop at 1151 Tibouchina Street, Montana Park, Pretoria.',
            ],
            [
                'slug' => 'wildman-mtubatuba',
                'name' => 'Wildman Mtubatuba',
                'province' => Province::KwaZuluNatal,
                'town' => 'Mtubatuba',
                'metro' => null,
                'phone' => '082 224 9734',
                'description' => 'Hunting and firearms shop at Shops 2-3, Oriole Centre, Mtubatuba.',
            ],
            [
                'slug' => 'wildman-nelspruit',
                'name' => 'Wildman Nelspruit',
                'province' => Province::Mpumalanga,
                'town' => 'Nelspruit',
                'metro' => null,
                'phone' => '013 741 5330',
                'description' => 'Hunting and firearms shop at Shop 8, Riverside Junction, Madiba Drive, Nelspruit.',
            ],
            [
                'slug' => 'wildman-piet-retief',
                'name' => 'Wildman Piet Retief',
                'province' => Province::Mpumalanga,
                'town' => 'Piet Retief',
                'metro' => null,
                'phone' => '083 465 1962',
                'description' => 'Hunting and firearms shop at 14A Von Brandis Street, corner of Meyer Street, Piet Retief.',
            ],
            [
                'slug' => 'wildman-pietermaritzburg',
                'name' => 'Wildman Pietermaritzburg',
                'province' => Province::KwaZuluNatal,
                'town' => 'Pietermaritzburg',
                'metro' => null,
                'phone' => '033 940 0131',
                'description' => 'Hunting and firearms shop at 6A Maritzburg Arch, 39–45 Chief Albert Luthuli Street, Pietermaritzburg.',
            ],
            [
                'slug' => 'wildman-polokwane',
                'name' => 'Wildman Polokwane',
                'province' => Province::Limpopo,
                'town' => 'Polokwane',
                'metro' => null,
                'phone' => '015 000 0320',
                'description' => 'Hunting and firearms shop at Shop 63, Thornhill Centre, corner of Veldspaat and Munnik Avenue, Bendor, Polokwane.',
            ],
            [
                'slug' => 'wildman-potchefstroom',
                'name' => 'Wildman Potchefstroom',
                'province' => Province::NorthWest,
                'town' => 'Potchefstroom',
                'metro' => null,
                'phone' => '018 297 4702',
                'description' => 'Hunting and firearms shop at Walter Sisulu Lane, Miederpark, Potchefstroom.',
            ],
            [
                'slug' => 'wildman-reitz',
                'name' => 'Wildman Reitz',
                'province' => Province::FreeState,
                'town' => 'Reitz',
                'metro' => null,
                'phone' => '058 303 0510',
                'description' => 'Hunting and firearms shop at VKB Trading, 35 Staatspresident CR Swart Street, Reitz.',
            ],
            [
                'slug' => 'wildman-robertson',
                'name' => 'Wildman Robertson',
                'province' => Province::WesternCape,
                'town' => 'Robertson',
                'metro' => null,
                'phone' => '083 625 6370',
                'description' => 'Hunting and firearms shop at 8 Voortrekker Avenue, Robertson.',
            ],
            [
                'slug' => 'wildman-secunda',
                'name' => 'Wildman Secunda',
                'province' => Province::Mpumalanga,
                'town' => 'Secunda',
                'metro' => null,
                'phone' => '017 631 3656',
                'description' => 'Hunting and firearms shop at 16 Scheepers Street, Secunda.',
            ],
            [
                'slug' => 'wildman-silver-lakes',
                'name' => 'Wildman Silver Lakes',
                'province' => Province::Gauteng,
                'town' => 'Equestria',
                'metro' => GautengMetro::Pretoria,
                'phone' => '012 880 1808',
                'gunsmith' => true,
                'description' => 'Hunting and firearms shop at Linton\'s Corner, Lynnwood Road and Solomon Mahlangu Drive, Equestria. Includes a 100 m range and an on-site gunsmith.',
            ],
            [
                'slug' => 'wildman-vanderbijlpark',
                'name' => 'Wildman Vanderbijlpark',
                'province' => Province::Gauteng,
                'town' => 'Vanderbijlpark',
                'metro' => GautengMetro::Vaal,
                'phone' => '072 300 3913',
                'description' => 'Hunting and firearms shop on Frikkie Meyer Boulevard, Vanderbijlpark, with an on-site shooting range.',
            ],
            [
                'slug' => 'wildman-vlt',
                'name' => 'Wildman Vlt',
                'province' => Province::Gauteng,
                'town' => 'Kilner Park',
                'metro' => GautengMetro::Pretoria,
                'phone' => '066 225 8698',
                'description' => 'Hunting and firearms shop at 1 Medical Centre, 255 Anna Wilson Street, Kilner Park, Pretoria.',
            ],
            [
                'slug' => 'wildman-hartswater',
                'name' => 'Wildman Hinterland Hartswater',
                'province' => Province::NorthernCape,
                'town' => 'Hartswater',
                'metro' => null,
                'phone' => '076 904 2658',
                'description' => 'Hunting and firearms shop at the corner of Pokwani and DF Malan Street, Hartswater.',
            ],
        ];
    }
};
