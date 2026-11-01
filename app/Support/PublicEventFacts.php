<?php

namespace App\Support;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\Province;
use App\Models\Event;

/**
 * Public facts for one event, shared by the page, meta description,
 * JSON-LD, feeds and calendar files.
 *
 * Prices stay on the event row. A member fee is never presented as
 * the general entry fee. An unknown fee is omitted. A missing venue
 * is named as unconfirmed when a province is already known.
 */
final class PublicEventFacts
{
    public function __construct(private Event $event) {}

    public static function for(Event $event): self
    {
        return new self($event);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public function priceRows(): array
    {
        $rows = [];
        $entry = $this->money($this->event->entry_fee_cents);
        $member = $this->money($this->event->member_fee_cents);

        if ($entry !== null && $member !== null) {
            $rows[] = ['Entry', $entry];
            $rows[] = ['Member entry', $member];
        } elseif ($entry !== null) {
            $rows[] = ['Entry', $entry];
        } elseif ($member !== null) {
            $rows[] = ['Member entry', $member];
        }

        return $rows;
    }

    public function priceSentence(): ?string
    {
        $sentences = [];

        foreach ($this->priceRows() as [$label, $amount]) {
            $sentences[] = $label.' is '.$amount.'.';
        }

        return $sentences === [] ? null : implode(' ', $sentences);
    }

    /**
     * Schema.org Offer nodes. One offer stays an object at the call
     * site when that is all we know; two prices stay a list so neither
     * is dropped.
     *
     * @return list<array<string, mixed>>
     */
    public function schemaOffers(): array
    {
        $url = $this->event->entry_url ?: $this->event->publicUrl();
        $availability = $this->offerAvailability();
        $offers = [];

        foreach ($this->pricedOffers() as [$name, $cents]) {
            $offer = [
                '@type' => 'Offer',
                'name' => $name,
                'url' => $url,
                'priceCurrency' => 'ZAR',
                'price' => number_format($cents / 100, 2, '.', ''),
                'validFrom' => $this->validFrom(),
            ];

            if ($availability !== null) {
                $offer['availability'] = $availability;
            }

            $offers[] = $offer;
        }

        if ($offers !== []) {
            return $offers;
        }

        $offer = [
            '@type' => 'Offer',
            'url' => $url,
            'priceCurrency' => 'ZAR',
            'validFrom' => $this->validFrom(),
        ];

        if ($availability !== null) {
            $offer['availability'] = $availability;
        }

        return [$offer];
    }

    public function venueLine(): ?string
    {
        $this->event->loadMissing(['venue', 'hostOrganisation']);

        $listed = $this->event->listedLocation();

        if ($listed !== null) {
            return $listed;
        }

        $province = $this->province();

        if ($province instanceof Province) {
            return 'Venue to be confirmed — '.$province->getLabel();
        }

        return null;
    }

    public function province(): ?Province
    {
        $this->event->loadMissing(['venue', 'hostOrganisation']);

        return $this->event->venue?->province ?? $this->event->hostOrganisation?->province;
    }

    public function provinceLabel(): ?string
    {
        return $this->province()?->getLabel();
    }

    public function machineDescription(): string
    {
        $parts = [];

        if (filled($this->event->description)) {
            $parts[] = trim((string) $this->event->description);
        }

        $venue = $this->venueLine();

        if ($venue !== null && ! str_contains(implode("\n", $parts), $venue)) {
            $parts[] = $venue;
        }

        if ($this->event->status === EventStatus::Planned) {
            $parts[] = $this->event->status->getLabel();
        }

        $price = $this->priceSentence();

        if ($price !== null && ! str_contains(implode("\n", $parts), $price)) {
            $parts[] = $price;
        }

        return implode(' ', $parts);
    }

    /**
     * ISO dates in the imported description that are not the listed
     * start or end. The description is left unchanged.
     *
     * @return list<string>
     */
    public function conflictingDescriptionDates(): array
    {
        $description = (string) $this->event->description;

        if ($description === '' || $this->event->starts_at === null) {
            return [];
        }

        preg_match_all('/\b(20\d{2}-\d{2}-\d{2})\b/', $description, $matches);

        $timezone = (string) config('app.timezone');
        $listed = array_filter([
            $this->event->starts_at->timezone($timezone)->toDateString(),
            $this->event->ends_at?->timezone($timezone)->toDateString(),
        ]);

        $conflicts = [];

        foreach (array_unique($matches[1] ?? []) as $date) {
            $parsed = date_create_from_format('Y-m-d', $date);

            if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                continue;
            }

            if (! in_array($date, $listed, true)) {
                $conflicts[] = $date;
            }
        }

        return $conflicts;
    }

    public static function reviewNotes(Event $event): ?string
    {
        $notes = [];
        $conflicts = self::for($event)->conflictingDescriptionDates();

        if ($conflicts !== []) {
            $notes[] = 'Description mentions '.implode(', ', $conflicts).', which is not the listed start or end date. Confirm the source before rewriting the description.';
        }

        if ($event->level === EventLevel::Club && preg_match('/\bNationals?\b/u', $event->title) === 1) {
            $notes[] = 'Title says national but the level is Club. Confirm the classification before changing it.';
        }

        return $notes === [] ? null : implode("\n", $notes);
    }

    /**
     * @return list<array{0: string, 1: int}>
     */
    private function pricedOffers(): array
    {
        $offers = [];

        if ($this->event->entry_fee_cents !== null) {
            $offers[] = ['Entry', (int) $this->event->entry_fee_cents];
        }

        if ($this->event->member_fee_cents !== null) {
            $offers[] = ['Member entry', (int) $this->event->member_fee_cents];
        }

        return $offers;
    }

    private function money(?int $cents): ?string
    {
        return Money::rand($cents);
    }

    private function validFrom(): string
    {
        return ($this->event->created_at ?? now())
            ->timezone((string) config('app.timezone'))
            ->toIso8601String();
    }

    private function offerAvailability(): ?string
    {
        if ($this->event->status === EventStatus::Planned) {
            return null;
        }

        if ($this->event->status === EventStatus::Cancelled) {
            return 'https://schema.org/Discontinued';
        }

        if ($this->event->status === EventStatus::Postponed) {
            return 'https://schema.org/PreOrder';
        }

        $capacity = (int) ($this->event->capacity ?? 0);
        $taken = (int) ($this->event->entries_taken ?? 0);

        if ($capacity > 0 && $taken >= $capacity) {
            return 'https://schema.org/SoldOut';
        }

        if ($capacity > 0 && $taken >= (int) ($capacity * 0.9)) {
            return 'https://schema.org/LimitedAvailability';
        }

        return 'https://schema.org/InStock';
    }
}
