<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

#[Signature('listings:missing-emails')]
#[Description('List published clubs, businesses, and events that have no email address')]
class ListMissingListingEmails extends Command
{
    public function handle(): int
    {
        $clubs = $this->organisationsMissingEmail();
        $businesses = $this->businessesMissingEmail();
        $events = $this->eventsMissingEmail();

        $this->components->info(sprintf(
            'Missing emails: %d clubs, %d businesses, %d events.',
            $clubs->count(),
            $businesses->count(),
            $events->count(),
        ));

        $this->newLine();
        $this->line('Clubs');
        $this->table(
            ['Name', 'Place', 'URL'],
            $clubs->map(fn (Organisation $organisation): array => [
                $organisation->name,
                $this->place($organisation->town, $organisation->province?->getLabel()),
                $organisation->isFederationListing()
                    ? route('federations.show', $organisation->slug)
                    : route('clubs.show', $organisation->slug),
            ])->all(),
        );

        $this->line('Businesses');
        $this->table(
            ['Name', 'Place', 'URL'],
            $businesses->map(fn (Provider $provider): array => [
                $provider->name,
                $this->place($provider->town, $provider->province?->getLabel()),
                route('suppliers.show', $provider),
            ])->all(),
        );

        $this->line('Events');
        $this->line('Host email is the club address already on file. A dash means the event has no contact at all.');
        $this->table(
            ['Match', 'When', 'Host', 'Host email', 'URL'],
            $events->map(fn (Event $event): array => [
                $event->title,
                $event->starts_at?->format('Y-m-d') ?? '',
                $event->hostOrganisation?->name ?? '',
                filled($event->hostOrganisation?->email) ? $event->hostOrganisation->email : '—',
                $event->publicUrl(),
            ])->all(),
        );

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Organisation>
     */
    private function organisationsMissingEmail()
    {
        return Organisation::query()
            ->where('status', ListingStatus::Published)
            ->where(fn (Builder $query) => $this->whereBlank($query, 'email'))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Provider>
     */
    private function businessesMissingEmail()
    {
        return Provider::query()
            ->where('status', ListingStatus::Published)
            ->where(fn (Builder $query) => $this->whereBlank($query, 'email'))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Event>
     */
    private function eventsMissingEmail()
    {
        return Event::query()
            ->with('hostOrganisation')
            ->where('status', '!=', EventStatus::Draft)
            ->where(fn (Builder $query) => $this->whereBlank($query, 'contact_email'))
            ->orderBy('starts_at')
            ->get();
    }

    private function whereBlank(Builder $query, string $column): void
    {
        $query->whereNull($column)->orWhere($column, '');
    }

    private function place(?string $town, ?string $province): string
    {
        return collect([$town, $province])->filter()->implode(', ');
    }
}
