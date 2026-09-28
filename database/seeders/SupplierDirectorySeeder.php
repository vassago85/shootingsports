<?php

namespace Database\Seeders;

use App\Enums\GautengMetro;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\ProviderTier;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Provider;
use Illuminate\Database\Seeder;

/**
 * Staff directory of gun shops and related suppliers.
 * Basic listing fields only. A picture is part of the enhanced (featured) tier.
 */
class SupplierDirectorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->listings() as $listing) {
            Provider::query()->updateOrCreate(
                ['slug' => $listing['slug']],
                array_merge($this->defaults(), $listing, ['logo_path' => null]),
            );
        }
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_column((new self)->listings(), 'slug');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'tier' => ProviderTier::Free,
            'status' => ListingStatus::Published,
            'verification_state' => VerificationState::Unconfirmed,
            'source' => ListingSource::Staff,
            'logo_path' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listings(): array
    {
        return array_merge(
            $this->singleShops(),
            $this->safariOutdoor(),
            $this->wildman(),
            $this->specialists(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function singleShops(): array
    {
        return [
            [
                'slug' => 'feather-fur-and-target',
                'name' => 'Feather Fur and Target',
                'category' => ProviderCategory::ReloadingComponents,
                'services' => [ProviderCategory::Optics->value],
                'province' => Province::Gauteng,
                'town' => 'Faerie Glen',
                'metro' => GautengMetro::Pretoria,
                'email' => 'orders@ffat.co.za',
                'phone' => '087 821 6667',
                'website_url' => 'https://ffat.co.za',
                'tagline' => 'Reloading components, equipment, and optics in Pretoria.',
                'description' => 'Family-owned shop in Faerie Glen selling reloading components, reloading equipment, optics and mounts, and cleaning gear.',
            ],
            [
                'slug' => 'mc-tactical',
                'name' => 'MC Tactical',
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::Optics->value,
                    ProviderCategory::ChassisStocks->value,
                    ProviderCategory::ReloadingComponents->value,
                ],
                'province' => Province::Gauteng,
                'town' => 'Garsfontein',
                'metro' => GautengMetro::Pretoria,
                'email' => 'sales@mctactical.co.za',
                'phone' => '082 821 0420',
                'website_url' => 'https://mctactical.co.za',
                'tagline' => 'Firearms, optics, chassis, and reloading supplies in Pretoria.',
                'description' => 'Gun shop at 873 Patryshond Street, Garsfontein, selling firearms, scopes, chassis and stocks, and reloading components.',
            ],
            [
                'slug' => 'zimbi',
                'name' => 'Zimbi',
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::Ammunition->value,
                    ProviderCategory::Optics->value,
                    ProviderCategory::ReloadingComponents->value,
                    ProviderCategory::SafesStorage->value,
                ],
                'province' => Province::Gauteng,
                'town' => 'Persequor',
                'metro' => GautengMetro::Pretoria,
                'email' => 'zimbi@zimbi.co.za',
                'phone' => '012 349 1662',
                'website_url' => 'https://zimbi.co.za',
                'tagline' => 'Specialist hunting and firearms shop in Pretoria.',
                'description' => 'Hunting shop at Unit 1A, Persequor Close, 49 De Havilland Crescent, Persequor Techno Park, selling firearms, ammunition, optics, reloading components, and gun safes.',
            ],
            [
                'slug' => 'boomsticks',
                'name' => 'Boomsticks',
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::Ammunition->value,
                    ProviderCategory::Optics->value,
                    ProviderCategory::ReloadingComponents->value,
                ],
                'province' => Province::WesternCape,
                'town' => 'Paarl',
                'email' => 'onlinesales@boomsticks.co.za',
                'phone' => '021 020 0620',
                'website_url' => 'https://boomsticks.co.za',
                'tagline' => 'Firearms, ammunition, optics, and reloading supplies in Paarl.',
                'description' => 'Outdoor shop at 21 Station Street, corner of Tabak Street, Southern Paarl, selling firearms, ammunition, optics, and reloading components.',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function safariOutdoor(): array
    {
        $shared = [
            'category' => ProviderCategory::Dealer,
            'services' => [
                ProviderCategory::Ammunition->value,
                ProviderCategory::Optics->value,
                ProviderCategory::ReloadingComponents->value,
                ProviderCategory::SafesStorage->value,
            ],
            'email' => 'info@so.co.za',
            'website_url' => 'https://safarioutdoor.co.za',
            'tagline' => 'Hunting and firearms shop. Optics, ammunition, reloading, and rifle safes.',
        ];

        $stores = [
            ['slug' => 'safari-outdoor-pretoria', 'name' => 'Safari Outdoor Pretoria', 'province' => Province::Gauteng, 'town' => 'Lynnwood', 'metro' => GautengMetro::Pretoria, 'phone' => '086 122 2269', 'description' => 'Hunting and firearms shop at Lynnwood Bridge, Daventry Road and Lynnwood Road, Pretoria. Also stocks outdoor and fishing gear.'],
            ['slug' => 'safari-outdoor-johannesburg', 'name' => 'Safari Outdoor Johannesburg', 'province' => Province::Gauteng, 'town' => 'Sunninghill', 'metro' => GautengMetro::Johannesburg, 'phone' => '086 114 3545', 'description' => 'Hunting and firearms shop at 3 Achter Road, Rivonia Crossing, Sunninghill. Also stocks outdoor and fishing gear.'],
            ['slug' => 'safari-outdoor-east-rand', 'name' => 'Safari Outdoor East Rand', 'province' => Province::Gauteng, 'town' => 'Boksburg', 'phone' => '086 100 0071', 'description' => 'Hunting and firearms shop at 113 North Rand Road, Boksburg. Also stocks outdoor and fishing gear.'],
            ['slug' => 'safari-outdoor-west-rand', 'name' => 'Safari Outdoor West Rand', 'province' => Province::Gauteng, 'town' => 'Krugersdorp', 'phone' => '086 111 4324', 'description' => 'Hunting and firearms shop at Furrow Road, Cradlestone Mall, Krugersdorp. Also stocks outdoor and fishing gear.'],
            ['slug' => 'safari-outdoor-stellenbosch', 'name' => 'Safari Outdoor Stellenbosch', 'province' => Province::WesternCape, 'town' => 'Koelenhof', 'phone' => '086 111 4330', 'description' => 'Hunting and firearms shop at the corner of the R304 and Bottelary Road, Devon Place Centre, Koelenhof. Also stocks outdoor and fishing gear.'],
            ['slug' => 'safari-outdoor-nelspruit', 'name' => 'Safari Outdoor Nelspruit', 'province' => Province::Mpumalanga, 'town' => 'Mbombela', 'phone' => '013 813 5500', 'description' => 'Hunting and firearms shop at the corner of Samora Drive and Du Preez Street, Valley Hyper, Mbombela. Also stocks outdoor and fishing gear.'],
            ['slug' => 'safari-outdoor-bloemfontein', 'name' => 'Safari Outdoor Bloemfontein', 'province' => Province::FreeState, 'town' => 'Bloemfontein', 'phone' => '086 110 0019', 'description' => 'Hunting and firearms shop at the corner of Abrahamskraal and Dealesville Road, on the R64, Bloemfontein. Also stocks outdoor and fishing gear.'],
        ];

        return array_map(fn (array $store): array => array_merge($shared, $store), $stores);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function wildman(): array
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
            'website_url' => 'https://wildmanhuntingandoutdoor.com',
            'tagline' => 'Hunting and firearms shop. Optics, ammunition, reloading, and gun safes.',
        ];

        $stores = [
            ['slug' => 'wildman-bethlehem', 'name' => 'Wildman Bethlehem', 'province' => Province::FreeState, 'town' => 'Bethlehem', 'phone' => '058 303 0510', 'email' => 'orders@wildmanbeth.co.za', 'description' => 'Hunting and firearms shop on the second floor of the VKB Building, corner of Lindley and Pretorius Street, Bethlehem.'],
            ['slug' => 'wildman-brits', 'name' => 'Wildman Brits', 'province' => Province::NorthWest, 'town' => 'Brits', 'phone' => '062 073 0796', 'description' => 'Hunting and firearms shop at Stokkiesdraai Business Centre, 1 Rutgers Street, Brits.'],
            ['slug' => 'wildman-cape-gate', 'name' => 'Wildman Cape Gate', 'province' => Province::WesternCape, 'town' => 'Brackenfell', 'phone' => '021 981 8180', 'description' => 'Hunting and firearms shop at Shop LL01A, Cape Gate Lifestyle Centre, Brackenfell.'],
            ['slug' => 'wildman-centurion', 'name' => 'Wildman Centurion', 'province' => Province::Gauteng, 'town' => 'Centurion', 'metro' => GautengMetro::Pretoria, 'phone' => '082 925 2749', 'gunsmith' => true, 'description' => 'Hunting and firearms shop at Centurion Gate, Building C, corner of John Vorster and Akkerboom Street, Zwartkop. Indoor range and an on-site gunsmith.'],
            ['slug' => 'wildman-lephalale', 'name' => 'Wildman Ellisras', 'province' => Province::Limpopo, 'town' => 'Lephalale', 'phone' => '082 307 2940', 'description' => 'Hunting and firearms shop at 1 Booysen Street, Lephalale.'],
            ['slug' => 'wildman-ermelo', 'name' => 'Wildman Ermelo', 'province' => Province::Mpumalanga, 'town' => 'Ermelo', 'phone' => '083 560 3654', 'description' => 'Hunting and firearms shop at 59 Kerk Street, Ermelo.'],
            ['slug' => 'wildman-george', 'name' => 'Wildman George', 'province' => Province::WesternCape, 'town' => 'George', 'phone' => '064 802 7537', 'description' => 'Hunting and firearms shop at 38 Albert Street, George.'],
            ['slug' => 'wildman-douglas', 'name' => 'Wildman GWK Douglas', 'province' => Province::NorthernCape, 'town' => 'Douglas', 'phone' => '053 298 8450', 'description' => 'Hunting and firearms shop at 1 De Villiers Street, Douglas.'],
            ['slug' => 'wildman-hoedspruit', 'name' => 'Wildman Hoedspruit', 'province' => Province::Limpopo, 'town' => 'Hoedspruit', 'phone' => '071 882 9895', 'description' => 'Hunting and firearms shop at Junction, Riverside Park, Hoedspruit.'],
            ['slug' => 'wildman-kimberley', 'name' => 'Wildman Kimberley', 'province' => Province::NorthernCape, 'town' => 'Kimberley', 'phone' => '071 366 1043', 'description' => 'Hunting and firearms shop at 12 Fabricia Road, Kimberley.'],
            ['slug' => 'wildman-middelburg', 'name' => 'Wildman Middelburg', 'province' => Province::Mpumalanga, 'town' => 'Middelburg', 'phone' => '078 212 1058', 'description' => 'Hunting and firearms shop at 11 July Street, Middelburg.'],
            ['slug' => 'wildman-montana', 'name' => 'Wildman Montana', 'province' => Province::Gauteng, 'town' => 'Montana Park', 'metro' => GautengMetro::Pretoria, 'phone' => '072 635 5512', 'description' => 'Hunting and firearms shop at 1151 Tibouchina Street, Montana Park, Pretoria.'],
            ['slug' => 'wildman-mtubatuba', 'name' => 'Wildman Mtubatuba', 'province' => Province::KwaZuluNatal, 'town' => 'Mtubatuba', 'phone' => '082 224 9734', 'description' => 'Hunting and firearms shop at Shops 2-3, Oriole Centre, Mtubatuba.'],
            ['slug' => 'wildman-nelspruit', 'name' => 'Wildman Nelspruit', 'province' => Province::Mpumalanga, 'town' => 'Nelspruit', 'phone' => '013 741 5330', 'description' => 'Hunting and firearms shop at Shop 8, Riverside Junction, Madiba Drive, Nelspruit.'],
            ['slug' => 'wildman-piet-retief', 'name' => 'Wildman Piet Retief', 'province' => Province::Mpumalanga, 'town' => 'Piet Retief', 'phone' => '083 465 1962', 'description' => 'Hunting and firearms shop at 14A Von Brandis Street, corner of Meyer Street, Piet Retief.'],
            ['slug' => 'wildman-pietermaritzburg', 'name' => 'Wildman Pietermaritzburg', 'province' => Province::KwaZuluNatal, 'town' => 'Pietermaritzburg', 'phone' => '033 940 0131', 'description' => 'Hunting and firearms shop at 6A Maritzburg Arch, 39–45 Chief Albert Luthuli Street, Pietermaritzburg.'],
            ['slug' => 'wildman-polokwane', 'name' => 'Wildman Polokwane', 'province' => Province::Limpopo, 'town' => 'Polokwane', 'phone' => '015 000 0320', 'description' => 'Hunting and firearms shop at Shop 63, Thornhill Centre, corner of Veldspaat and Munnik Avenue, Bendor, Polokwane.'],
            ['slug' => 'wildman-potchefstroom', 'name' => 'Wildman Potchefstroom', 'province' => Province::NorthWest, 'town' => 'Potchefstroom', 'phone' => '018 297 4702', 'description' => 'Hunting and firearms shop at Walter Sisulu Lane, Miederpark, Potchefstroom.'],
            ['slug' => 'wildman-reitz', 'name' => 'Wildman Reitz', 'province' => Province::FreeState, 'town' => 'Reitz', 'phone' => '058 303 0510', 'description' => 'Hunting and firearms shop at VKB Trading, 35 Staatspresident CR Swart Street, Reitz.'],
            ['slug' => 'wildman-robertson', 'name' => 'Wildman Robertson', 'province' => Province::WesternCape, 'town' => 'Robertson', 'phone' => '083 625 6370', 'description' => 'Hunting and firearms shop at 8 Voortrekker Avenue, Robertson.'],
            ['slug' => 'wildman-secunda', 'name' => 'Wildman Secunda', 'province' => Province::Mpumalanga, 'town' => 'Secunda', 'phone' => '017 631 3656', 'description' => 'Hunting and firearms shop at 16 Scheepers Street, Secunda.'],
            ['slug' => 'wildman-silver-lakes', 'name' => 'Wildman Silver Lakes', 'province' => Province::Gauteng, 'town' => 'Equestria', 'metro' => GautengMetro::Pretoria, 'phone' => '012 880 1808', 'gunsmith' => true, 'description' => 'Hunting and firearms shop at Linton\'s Corner, Lynnwood Road and Solomon Mahlangu Drive, Equestria. Includes a 100 m range and an on-site gunsmith.'],
            ['slug' => 'wildman-vanderbijlpark', 'name' => 'Wildman Vanderbijlpark', 'province' => Province::Gauteng, 'town' => 'Vanderbijlpark', 'metro' => GautengMetro::Vaal, 'phone' => '072 300 3913', 'description' => 'Hunting and firearms shop on Frikkie Meyer Boulevard, Vanderbijlpark, with an on-site shooting range.'],
            ['slug' => 'wildman-vlt', 'name' => 'Wildman Vlt', 'province' => Province::Gauteng, 'town' => 'Kilner Park', 'metro' => GautengMetro::Pretoria, 'phone' => '066 225 8698', 'description' => 'Hunting and firearms shop at 1 Medical Centre, 255 Anna Wilson Street, Kilner Park, Pretoria.'],
            ['slug' => 'wildman-hartswater', 'name' => 'Wildman Hinterland Hartswater', 'province' => Province::NorthernCape, 'town' => 'Hartswater', 'phone' => '076 904 2658', 'description' => 'Hunting and firearms shop at the corner of Pokwani and DF Malan Street, Hartswater.'],
        ];

        return array_map(function (array $store) use ($shared, $services): array {
            $withGunsmith = $store['gunsmith'] ?? false;
            unset($store['gunsmith']);

            $listing = array_merge($shared, $store);

            if ($withGunsmith) {
                $listing['services'] = array_merge($services, [ProviderCategory::Gunsmith->value]);
            }

            return $listing;
        }, $stores);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function specialists(): array
    {
        return [
            [
                'slug' => 'gun-warrior',
                'name' => 'Gun Warrior',
                'category' => ProviderCategory::Dealer,
                'services' => [ProviderCategory::ChassisStocks->value],
                'province' => Province::Gauteng,
                'town' => 'Hennopspark',
                'metro' => GautengMetro::Pretoria,
                'email' => 'info@gunwarrior.co.za',
                'phone' => '012 653 6424',
                'website_url' => 'https://www.gunwarrior.co.za',
                'tagline' => 'Rifle chassis, silencers, and firearms in Centurion.',
                'description' => 'Chassis and silencer workshop at 106 Edward Avenue, Hennopspark, Centurion. Visits are by appointment.',
            ],
            [
                'slug' => 'axis-precision-worx',
                'name' => 'Axis Precision Worx',
                'category' => ProviderCategory::ChassisStocks,
                'services' => [ProviderCategory::ReloadingComponents->value],
                'province' => Province::WesternCape,
                'town' => 'Triangle Farm',
                'email' => 'info@axisprecisionworx.com',
                'phone' => '064 692 8888',
                'website_url' => 'https://www.axisprecisionworx.com',
                'tagline' => 'APW precision chassis, muzzle brakes, and reloading tools in Cape Town.',
                'description' => 'Manufacturer at 3A Micro Street, Triangle Farm, Cape Town, making carbon chassis, muzzle brakes, silencers, and reloading tools.',
            ],
            [
                'slug' => 'dave-sheer-guns-johannesburg',
                'name' => 'Dave Sheer Guns Johannesburg',
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::Ammunition->value,
                    ProviderCategory::Gunsmith->value,
                    ProviderCategory::Instructor->value,
                ],
                'province' => Province::Gauteng,
                'town' => 'Bramley',
                'metro' => GautengMetro::Johannesburg,
                'phone' => '011 440 0345',
                'website_url' => 'https://davesheer.com',
                'tagline' => 'Firearms, ammunition, gunsmithing, and training in Johannesburg.',
                'description' => 'Gun shop at 95 Forest Road, Bramley, selling firearms and ammunition, with repairs and training.',
            ],
            [
                'slug' => 'dave-sheer-guns-cape-town',
                'name' => 'Dave Sheer Guns Cape Town',
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::Ammunition->value,
                    ProviderCategory::Optics->value,
                    ProviderCategory::SafesStorage->value,
                    ProviderCategory::Gunsmith->value,
                    ProviderCategory::Instructor->value,
                ],
                'province' => Province::WesternCape,
                'town' => 'Durbanville',
                'email' => 'admin@davesheerct.com',
                'phone' => '021 007 2920',
                'website_url' => 'https://davesheerct.com',
                'tagline' => 'Firearms, ammunition, optics, gunsmithing, and an on-site range in Durbanville.',
                'description' => 'Gun shop at Shop 35A-D, Groot Phesantekraal View, corner of Klipheuwel and Okavango Drive, Durbanville, with an on-site range, optics, gun safes, and a workshop.',
            ],
            [
                'slug' => 'lock-n-load',
                'name' => "Lock 'n Load",
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::Optics->value,
                    ProviderCategory::ReloadingComponents->value,
                    ProviderCategory::ChassisStocks->value,
                    ProviderCategory::Gunsmith->value,
                ],
                'province' => Province::Gauteng,
                'town' => 'Erasmuskloof',
                'metro' => GautengMetro::Pretoria,
                'email' => 'info@locknload.pro',
                'phone' => '062 075 9670',
                'website_url' => 'https://locknload.pro',
                'tagline' => 'Bespoke firearms, optics, stocks, and gunsmithing in Pretoria.',
                'description' => 'Bespoke gun shop at 461 Lois Avenue, Erasmuskloof, selling firearms, optics, reloading equipment, and rifle stocks, with a gunsmithing workshop.',
            ],
            [
                'slug' => 'gunslinger',
                'name' => 'Gunslinger',
                'category' => ProviderCategory::Dealer,
                'services' => [
                    ProviderCategory::Optics->value,
                    ProviderCategory::Ammunition->value,
                    ProviderCategory::ReloadingComponents->value,
                    ProviderCategory::ChassisStocks->value,
                ],
                'province' => Province::Gauteng,
                'town' => 'Erasmuskloof',
                'metro' => GautengMetro::Pretoria,
                'email' => 'sales@gunslinger.bz',
                'phone' => '079 513 5099',
                'website_url' => 'https://gunslinger.bz',
                'tagline' => 'Precision firearms, optics, ammunition, and rifle stocks in Pretoria.',
                'description' => 'Firearms dealer and importer at 461 Lois Avenue, Erasmuskloof, supplying precision rifles, optics, ammunition, and rifle stocks.',
            ],
        ];
    }
}
