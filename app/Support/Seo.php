<?php

namespace App\Support;

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
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

        if (filled($organisation->description)) {
            $sentence .= ' '.$organisation->description;
        }

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

        $title = $venue->name.' — Shooting Range in '.$place;

        if ($distance !== null) {
            $title .= ' | '.$distance;
        }

        $sentence = $venue->name.' is a shooting range'.($place !== '' ? ' in '.$place : '').'.';

        if ($distance !== null) {
            $sentence .= ' Distances to '.$distance.'.';
        }

        if (filled($venue->address)) {
            $sentence .= ' '.$venue->address.'.';
        }

        if (filled($venue->notes)) {
            $sentence .= ' '.$venue->notes;
        }

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
        $venue = $event->venue?->name;

        $detail = collect([
            $discipline !== null ? $discipline.' Match' : 'Shooting match',
            $date,
            $venue,
        ])->filter()->implode(', ');

        $sentence = $event->title.' is a '.$detail.'.';

        if ($event->hostOrganisation !== null) {
            $sentence .= ' Hosted by '.$event->hostOrganisation->name.'.';
        }

        if (filled($event->description)) {
            $sentence .= ' '.$event->description;
        }

        return new self(
            title: $event->title.' — '.$detail,
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

        if (filled($provider->description)) {
            $sentence .= ' '.$provider->description;
        }

        return new self(
            title: $provider->name.' — '.$category.($place !== '' ? ' in '.$place : ''),
            description: self::clip($sentence),
            canonical: route('suppliers.show', $provider->slug),
            robots: Indexability::allows($provider) ? null : 'noindex,follow',
        );
    }

    public static function landing(Discipline $discipline, Province $province, string $description, bool $thin): self
    {
        return new self(
            title: $discipline->name.' Clubs in '.$province->getLabel().' — Ranges, Matches & Contacts',
            description: self::clip($description),
            canonical: route('clubs.landing', [$province->urlSlug(), $discipline->slug]),
            robots: $thin ? 'noindex,follow' : null,
        );
    }

    public static function discipline(Discipline $discipline, string $description): self
    {
        return new self(
            title: $discipline->name,
            description: self::clip($description),
            canonical: route('disciplines.show', $discipline->slug),
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
     * @param  Collection<int, Discipline>  $disciplines
     */
    private static function disciplineLabel(Collection $disciplines): ?string
    {
        $label = $disciplines->pluck('name')->filter()->take(3)->implode(', ');

        return $label !== '' ? $label : null;
    }
}
