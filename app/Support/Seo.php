<?php

namespace App\Support;

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\MagazineIssue;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * One title, description and canonical per public record.
 *
 * The layout appends the site name. These strings are the query the
 * page should rank for, built only from fields on the record.
 */
final readonly class Seo
{
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public ?string $robots = null,
    ) {}

    public static function forOrganisation(Organisation $organisation): self
    {
        $organisation->loadMissing('disciplines');

        $disciplines = self::disciplineLabel($organisation->disciplines);
        $place = collect([$organisation->town, $organisation->province?->getLabel()])->filter()->implode(', ');
        $kind = $organisation->isFederationListing() ? 'Shooting federation' : 'Shooting Club';
        $lead = $disciplines !== null ? $disciplines.' '.$kind : $kind;

        $title = $organisation->name.' — '.collect([$lead, $place])->filter()->implode(', ');

        $sentence = trim($organisation->name.' is a '.($disciplines !== null ? $disciplines.' ' : '').strtolower($kind).($place !== '' ? ' in '.$place : '').'.');

        $qualifiers = collect([
            $organisation->accredited ? 'Accredited' : null,
            $organisation->visitors_welcome ? 'Visitors welcome' : null,
        ])->filter()->implode('. ');

        if ($qualifiers !== '') {
            $sentence .= ' '.$qualifiers.'.';
        }

        if (filled($organisation->description)) {
            $sentence .= ' '.$organisation->description;
        }

        $sentence = UpcomingCopy::facts(
            Event::query()->upcoming()->where('host_organisation_id', $organisation->id),
        ).' '.$sentence;

        $canonical = $organisation->isFederationListing()
            ? route('federations.show', $organisation->slug)
            : route('clubs.show', $organisation->slug);

        return new self(
            title: $title,
            description: self::clip($sentence),
            canonical: $canonical,
            robots: Indexability::allows($organisation) ? null : 'noindex,follow',
        );
    }

    public static function forVenue(Venue $venue): self
    {
        $venue->loadMissing('disciplines');

        $place = collect([$venue->town, $venue->province?->getLabel()])->filter()->implode(', ');
        $distance = $venue->max_distance_m !== null
            ? number_format((int) $venue->max_distance_m).' m'
            : null;
        $disciplines = self::disciplineLabel($venue->disciplines);

        $title = $venue->name.' — Shooting Range in '.$place;

        if ($distance !== null) {
            $title .= ' | '.$distance;
        }

        $sentence = $venue->name.' is a shooting range'.($place !== '' ? ' in '.$place : '').'.';

        if ($disciplines !== null) {
            $sentence .= ' Hosts '.$disciplines.'.';
        }

        $stats = collect([
            $distance !== null ? 'distances to '.$distance : null,
            $venue->bay_count ? $venue->bay_count.' '.str('bay')->plural($venue->bay_count)->toString() : null,
            $venue->access?->getLabel(),
        ])->filter()->implode(', ');

        if ($stats !== '') {
            $sentence .= ' '.ucfirst($stats).'.';
        }

        if (filled($venue->address)) {
            $sentence .= ' '.$venue->address.'.';
        }

        if (filled($venue->description)) {
            $sentence .= ' '.$venue->description;
        } elseif (filled($venue->notes)) {
            $sentence .= ' '.$venue->notes;
        }

        $sentence = UpcomingCopy::facts(self::eventsAtVenue($venue)).' '.$sentence;

        return new self(
            title: $title,
            description: self::clip($sentence),
            canonical: route('ranges.show', $venue->slug),
            robots: Indexability::allows($venue) ? null : 'noindex,follow',
        );
    }

    public static function forEvent(Event $event): self
    {
        $event->loadMissing(['disciplines', 'venue', 'hostOrganisation']);

        $discipline = $event->primaryDiscipline()?->name ?? $event->disciplines->first()?->name;
        $date = $event->starts_at?->timezone(config('app.timezone'))->format('j M Y');
        $place = collect([$event->venue?->name, $event->venue?->town])->filter()->implode(', ');

        $title = collect([$event->title, $place !== '' ? $place : null, $date])->filter()->implode(' · ');

        $entry = match (true) {
            $event->entry_fee_cents !== null => 'Entry is R'.number_format($event->entry_fee_cents / 100, 2, '.', '').'.',
            filled($event->entry_url) => 'Entry details are linked from the event page.',
            default => 'Entry details are on the event page.',
        };

        $sentence = collect([
            $event->hostOrganisation !== null ? 'Hosted by '.$event->hostOrganisation->name.'.' : null,
            $discipline !== null ? $discipline.' match.' : null,
            $event->status !== null ? 'Status: '.$event->status->getLabel().'.' : null,
            $entry,
            filled($event->description) ? $event->description : null,
        ])->filter()->implode(' ');

        return new self(
            title: $title,
            description: self::clip($sentence),
            canonical: $event->publicUrl(),
            robots: Indexability::allows($event) ? null : 'noindex,follow',
        );
    }

    public static function forProvider(Provider $provider): self
    {
        $place = collect([$provider->town, $provider->province?->getLabel()])->filter()->implode(', ');
        $category = $provider->category->getLabel();

        $sentence = $provider->name.' is a '.$category.($place !== '' ? ' in '.$place : '').'.';

        if (filled($provider->tagline)) {
            $sentence .= ' '.$provider->tagline;
        } elseif (filled($provider->description)) {
            $sentence .= ' '.$provider->description;
        }

        return new self(
            title: $provider->name.' — '.$category.($place !== '' ? ' in '.$place : ''),
            description: self::clip($sentence),
            canonical: route('suppliers.show', $provider->slug),
            robots: Indexability::allows($provider) ? null : 'noindex,follow',
        );
    }

    public static function landing(Discipline $discipline, Province $province, string $description, bool $thin, int $upcoming): self
    {
        return new self(
            title: DisciplinePages::title($discipline, $province, $upcoming),
            description: self::clip($description),
            canonical: route('disciplines.province', [$discipline->slug, $province->urlSlug()]),
            robots: $thin ? 'noindex,follow' : null,
        );
    }

    public static function discipline(Discipline $discipline, string $description, int $upcoming): self
    {
        return new self(
            title: DisciplinePages::title($discipline, null, $upcoming),
            description: self::clip($description),
            canonical: route('disciplines.show', $discipline->slug),
        );
    }

    public static function forMagazineIndex(): self
    {
        return new self(
            title: 'Magazines',
            description: self::clip('Published magazines. Each issue names its owner, so more than one publisher can host here.'),
            canonical: route('magazines.index'),
        );
    }

    public static function forMagazineIssue(MagazineIssue $issue, bool $previewing = false): self
    {
        $title = $issue->title;
        $description = filled($issue->description)
            ? (string) $issue->description
            : $issue->publication.' — '.$issue->title;

        return new self(
            title: $title,
            description: self::clip($description),
            canonical: route('magazines.show', $issue->slug),
            robots: $previewing || $issue->requiresLogin() ? 'noindex,nofollow' : null,
        );
    }

    public static function disciplineAbout(Discipline $discipline): self
    {
        $description = filled($discipline->body)
            ? (string) $discipline->body
            : (string) $discipline->short_blurb;

        return new self(
            title: 'About '.$discipline->name,
            description: self::clip($description),
            canonical: route('disciplines.about', $discipline->slug),
        );
    }

    public static function clip(string $text, int $limit = 158): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit);
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space >= (int) ($limit * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t.,;:-");
    }

    /**
     * @return Builder<Event>
     */
    private static function eventsAtVenue(Venue $venue): Builder
    {
        return Event::query()
            ->upcoming()
            ->where(function (Builder $query) use ($venue): void {
                $query->where('venue_id', $venue->id)
                    ->orWhereHas('venues', fn (Builder $venues) => $venues->where('venues.id', $venue->id));
            });
    }

    /**
     * @param  Collection<int, Discipline>  $disciplines
     */
    private static function disciplineLabel(Collection $disciplines): ?string
    {
        $label = $disciplines->pluck('name')->filter()->take(3)->implode(', ');

        return $label !== '' ? $label : null;
    }
}
