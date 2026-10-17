<?php

namespace App\Filament\Pages;

use App\Enums\VerificationState;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class VerificationDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Verification';

    protected static ?string $title = 'Verification queue';

    protected string $view = 'filament.pages.verification-dashboard';

    /**
     * Phase 2 moves the verification queue onto the Clubs, Ranges and
     * Industry list pages as tabs. The dashboard page stays reachable
     * at /admin/verification-dashboard so bookmarks still work, but
     * it is no longer in the top-level nav.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function getCounts(): array
    {
        $states = VerificationState::cases();
        $models = [
            'Organisations' => Organisation::query(),
            'Venues' => Venue::query(),
            'Providers' => Provider::query(),
        ];

        $rows = [];

        foreach ($models as $label => $query) {
            $row = [];

            foreach ($states as $state) {
                $row[$state->value] = (clone $query)->where('verification_state', $state)->count();
            }

            $rows[$label] = $row;
        }

        return $rows;
    }
}
