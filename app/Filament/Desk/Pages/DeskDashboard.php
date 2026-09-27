<?php

namespace App\Filament\Desk\Pages;

use App\Enums\EventStatus;
use App\Filament\Desk\Resources\Events\EventResource;
use App\Filament\Desk\Resources\Organisations\OrganisationResource;
use App\Models\Event;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
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

        $baseQuery = Event::query()->whereIn('host_organisation_id', $orgIds);

        $drafts = $orgIds->isEmpty() ? 0 : (clone $baseQuery)->where('status', EventStatus::Draft)->count();
        $upcoming = $orgIds->isEmpty() ? 0 : (clone $baseQuery)->upcoming()->count();
        $nextEvents = $orgIds->isEmpty()
            ? new Collection
            : (clone $baseQuery)
                ->upcoming()
                ->orderBy('starts_at')
                ->limit(5)
                ->get();

        return [
            'name' => $user?->name ?? 'Director',
            'listingCount' => $orgIds->count(),
            'eventCount' => $orgIds->isEmpty() ? 0 : (clone $baseQuery)->count(),
            'draftCount' => $drafts,
            'upcomingCount' => $upcoming,
            'nextEvents' => $nextEvents->map(fn (Event $event): array => [
                'title' => $event->title,
                'date' => $event->starts_at->timezone('Africa/Johannesburg')->format('j M Y'),
                'status' => $event->status?->getLabel() ?? '',
                'href' => EventResource::getUrl('edit', ['record' => $event]),
            ])->all(),
            'createListingUrl' => OrganisationResource::getUrl('create'),
            'listingsUrl' => OrganisationResource::getUrl('index'),
            'createEventUrl' => EventResource::getUrl('create'),
            'eventsUrl' => EventResource::getUrl('index'),
            'claimUrl' => ClaimListing::getUrl(),
        ];
    }
}
