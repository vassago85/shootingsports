<?php

namespace App\Filament\Desk\Pages;

use App\Filament\Desk\Resources\Events\EventResource;
use App\Filament\Desk\Resources\Organisations\OrganisationResource;
use App\Models\Event;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class DeskDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'Match director desk';

    protected static ?string $slug = '';

    protected static ?int $navigationSort = -10;

    protected string $view = 'filament.desk.pages.desk-dashboard';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Auth::user();
        $orgIds = $user?->organisations()->pluck('organisations.id') ?? collect();

        return [
            'name' => $user?->name ?? 'Director',
            'listingCount' => $orgIds->count(),
            'eventCount' => $orgIds->isEmpty()
                ? 0
                : Event::query()->whereIn('host_organisation_id', $orgIds)->count(),
            'createListingUrl' => OrganisationResource::getUrl('create'),
            'listingsUrl' => OrganisationResource::getUrl('index'),
            'createEventUrl' => EventResource::getUrl('create'),
            'eventsUrl' => EventResource::getUrl('index'),
            'claimUrl' => ClaimListing::getUrl(),
        ];
    }
}
