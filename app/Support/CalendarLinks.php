<?php

namespace App\Support;

use App\Enums\DisciplineFamily;
use App\Enums\Division;
use App\Enums\EventKind;
use App\Enums\Province;

/**
 * Shareable calendar, month and map query strings.
 *
 * Built only from the public filter state on the calendar components.
 * Livewire update requests also carry a CSRF token and a component
 * snapshot; those must never be copied into a navigation URL.
 */
final class CalendarLinks
{
    public static function listUrl(object $component): string
    {
        return route('calendar', self::parameters($component, false));
    }

    public static function monthUrl(object $component): string
    {
        return route('calendar.month', self::parameters($component, true));
    }

    public static function mapUrl(object $component): string
    {
        return route('map', self::parameters($component, false));
    }

    /**
     * @return array<string, string>
     */
    public static function parameters(object $component, bool $includeMonth): array
    {
        $family = self::family(self::read($component, 'family'));
        $lat = self::coordinate(self::read($component, 'lat'), -90, 90);
        $lng = self::coordinate(self::read($component, 'lng'), -180, 180);

        $parameters = array_filter([
            'family' => $family,
            'novice' => self::flag(self::read($component, 'novice')) ? '1' : null,
            'confirmed' => self::flag(self::read($component, 'confirmed')) ? '1' : null,
            'weekend' => self::flag(self::read($component, 'weekend')) ? '1' : null,
            'discipline' => self::slug(self::read($component, 'discipline')),
            'province' => self::provinces(self::read($component, 'province')),
            'radius' => self::radius(self::read($component, 'radius')),
            'near' => self::near(self::read($component, 'near')),
            'lat' => ($lat !== null && $lng !== null) ? $lat : null,
            'lng' => ($lat !== null && $lng !== null) ? $lng : null,
            'kind' => self::kind(self::read($component, 'kind')),
            'from' => self::date(self::read($component, 'from')),
            'to' => self::date(self::read($component, 'to')),
            'month' => $includeMonth ? self::month(self::read($component, 'month')) : null,
        ], fn (mixed $value): bool => is_string($value) && $value !== '');

        return $parameters;
    }

    private static function read(object $component, string $key): mixed
    {
        return property_exists($component, $key) ? $component->{$key} : null;
    }

    private static function family(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value === 'all') {
            return null;
        }

        if (Division::tryFrom($value) instanceof Division || DisciplineFamily::tryFrom($value) instanceof DisciplineFamily) {
            return $value;
        }

        return null;
    }

    private static function kind(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return EventKind::tryFrom($value)?->value;
    }

    private static function slug(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $slug = strtolower(trim($value));

        if ($slug === '' || strlen($slug) > 80 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            return null;
        }

        return $slug;
    }

    private static function provinces(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $slugs = [];

        foreach (preg_split('/\s*,\s*/', $value) ?: [] as $part) {
            $province = Province::fromUrlSlug(strtolower(trim($part)));

            if ($province instanceof Province) {
                $slugs[] = $province->urlSlug();
            }
        }

        $slugs = array_values(array_unique($slugs));

        return $slugs === [] ? null : implode(',', $slugs);
    }

    private static function radius(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $radius = (int) $value;

        if ($radius < 1 || $radius > 500) {
            return null;
        }

        return (string) $radius;
    }

    private static function coordinate(mixed $value, float $min, float $max): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if ($number < $min || $number > $max) {
            return null;
        }

        return rtrim(rtrim(number_format($number, 6, '.', ''), '0'), '.');
    }

    private static function near(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $near = trim((string) preg_replace('/\s+/', ' ', strip_tags($value)));

        if ($near === '' || strlen($near) > 80 || preg_match('/^[\p{L}\p{N}\s,.\'’-]+$/u', $near) !== 1) {
            return null;
        }

        return $near;
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $parsed = date_create_from_format('Y-m-d', $value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    private static function month(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    private static function flag(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1';
    }
}
