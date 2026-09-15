<?php

namespace App\Filament\Pages;

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\ListingStatus;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Placements\PlacementResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Enquiry;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class StaffDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Home';

    protected static ?string $title = 'Staff desk';

    protected static ?string $slug = '';

    protected static ?int $navigationSort = -20;

    protected string $view = 'filament.pages.staff-dashboard';

    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_staff;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $pendingOrgs = Organisation::query()->where('status', ListingStatus::Pending)->count();
        $newEnquiries = Enquiry::query()->where('status', EnquiryStatus::New)->count();
        $upcoming = Event::query()->upcoming()->count();

        return [
            'name' => auth()->user()?->name ?? 'Staff',
            'pendingOrgs' => $pendingOrgs,
            'newEnquiries' => $newEnquiries,
            'upcoming' => $upcoming,
            'waitlistThisMonth' => $this->waitlistThisMonth(),
            'newMatchDirectors' => $this->newMatchDirectorsThisMonth(),
            'orgsUrl' => OrganisationResource::getUrl('index'),
            'createOrgUrl' => OrganisationResource::getUrl('create'),
            'eventsUrl' => EventResource::getUrl('index'),
            'createEventUrl' => EventResource::getUrl('create'),
            'enquiriesUrl' => EnquiryResource::getUrl('index'),
            'placementsUrl' => PlacementResource::getUrl('index'),
            'emailUrl' => ManageMailSettings::getUrl(),
            'verificationUrl' => VerificationDashboard::getUrl(),
            'usersUrl' => UserResource::getUrl('index'),
        ];
    }

    /**
     * Match director signups this calendar month — the review lane
     * introduced by the open-signup MD flow. Kept as a raw count with
     * a "review" deep link rather than a table on the dashboard; if
     * volume grows, promote it to a dedicated widget.
     */
    private function newMatchDirectorsThisMonth(): int
    {
        return User::query()
            ->where('is_match_director', true)
            ->where('is_staff', false)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->count();
    }

    /**
     * Pro waitlist signups this calendar month, bucketed by trigger.
     * Postgres-friendly: reads context->>'trigger' via Eloquent's JSON
     * accessor rather than a driver-specific SQL fragment.
     *
     * @return array<string, int>
     */
    private function waitlistThisMonth(): array
    {
        $rows = Enquiry::query()
            ->where('type', EnquiryType::ProWaitlist)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get(['context']);

        $counts = [];
        foreach ($rows as $row) {
            $trigger = data_get($row->context, 'trigger') ?: 'unknown';
            $counts[$trigger] = ($counts[$trigger] ?? 0) + 1;
        }

        arsort($counts);

        return $counts;
    }
}
