<?php

namespace Database\Seeders;

use App\Enums\AdPage;
use App\Enums\PlacementSlot;
use App\Models\AdSlot;
use Illuminate\Database\Seeder;

class AdSlotSeeder extends Seeder
{
    public function run(): void
    {
        $slots = [
            [AdPage::Home, PlacementSlot::Leaderboard, 'Home leaderboard', 250000],
            [AdPage::Calendar, PlacementSlot::Leaderboard, 'Calendar leaderboard', 200000],
            [AdPage::Suppliers, PlacementSlot::InFeedNative, 'Suppliers in-feed', 150000],
            [AdPage::Ranges, PlacementSlot::InFeedNative, 'Ranges in-feed', 150000],
            [AdPage::Disciplines, PlacementSlot::CategorySponsor, 'Discipline sponsor', 180000],
            [AdPage::Matches, PlacementSlot::InFeedNative, 'Match page native', 120000],
        ];

        foreach ($slots as [$page, $slot, $name, $price]) {
            AdSlot::query()->updateOrCreate(
                [
                    'page' => $page,
                    'slot' => $slot,
                ],
                [
                    'name' => $name,
                    'price_cents' => $price,
                    'is_active' => true,
                    'notes' => 'Staff rate card — invoice off-site.',
                ],
            );
        }
    }
}
