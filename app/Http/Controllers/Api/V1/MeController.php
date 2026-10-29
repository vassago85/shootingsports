<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Province;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Venue;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }

    /**
     * Update the authenticated shooter's discovery preferences. Only
     * covers the fields the mobile app lets the user edit directly
     * (home + extra provinces, travel radius). Account-sensitive
     * fields (email, password, plan) stay on the web profile pages
     * where they can go through the full verification/reauth flow.
     *
     * Province values accept either the enum string ("gauteng") or
     * the URL slug ("gauteng" / "kwazulu-natal") so the mobile client
     * can round-trip whichever representation it already has from
     * the GET response.
     */
    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'home_province' => ['sometimes', 'nullable', 'string'],
            'extra_provinces' => ['sometimes', 'nullable', 'array'],
            'extra_provinces.*' => ['string'],
            'travel_radius_km' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:2000'],
            // Home range — accept either the venue id (int) or the
            // venue slug (string). The feed uses this + the above
            // travel_radius_km to populate the Near-you bucket.
            'home_venue_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'home_venue_slug' => ['sometimes', 'nullable', 'string'],
        ]);

        $user = $request->user();

        if (array_key_exists('home_province', $data)) {
            $user->home_province = $this->resolveProvince($data['home_province']);
        }

        if (array_key_exists('extra_provinces', $data)) {
            $user->extra_provinces = $this->resolveExtraProvinces($data['extra_provinces']);
        }

        if (array_key_exists('travel_radius_km', $data)) {
            $user->travel_radius_km = $data['travel_radius_km'];
        }

        if (array_key_exists('home_venue_id', $data) || array_key_exists('home_venue_slug', $data)) {
            $user->home_venue_id = $this->resolveHomeVenueId(
                $data['home_venue_id'] ?? null,
                $data['home_venue_slug'] ?? null,
            );
        }

        $user->save();

        return UserResource::make($user->refresh());
    }

    private function resolveProvince(?string $input): ?Province
    {
        if ($input === null || $input === '') {
            return null;
        }

        // Accept either the raw enum value ("gauteng") or the URL
        // slug variant ("kwazulu-natal"). Returns null for anything
        // that doesn't resolve, which the validator above does not
        // reject here — we fall through to abort(422).
        $resolved = Province::tryFrom($input) ?? Province::fromUrlSlug($input);

        abort_if($resolved === null, 422, "Unknown province: {$input}");

        return $resolved;
    }

    /**
     * @param  array<int, string>|null  $values
     * @return array<int, string>|null
     */
    private function resolveExtraProvinces(?array $values): ?array
    {
        if ($values === null || $values === []) {
            return null;
        }

        $resolved = collect($values)
            ->map(fn (string $value) => Province::tryFrom($value) ?? Province::fromUrlSlug($value))
            ->filter()
            ->unique(fn (Province $province) => $province->value)
            ->map(fn (Province $province) => $province->value)
            ->values()
            ->all();

        return $resolved === [] ? null : $resolved;
    }

    /**
     * Resolve the picked home-range venue, preferring an explicit
     * id over a slug lookup. Returning null is a legitimate clear
     * ("stop using a home range"). An unknown id/slug 422s so a
     * typo doesn't silently wipe the setting.
     */
    private function resolveHomeVenueId(?int $id, ?string $slug): ?int
    {
        if ($id !== null) {
            abort_unless(Venue::query()->whereKey($id)->exists(), 422, "Unknown venue id: {$id}");

            return $id;
        }

        if ($slug !== null && $slug !== '') {
            $venue = Venue::query()->where('slug', $slug)->first();
            abort_if($venue === null, 422, "Unknown venue slug: {$slug}");

            return $venue->id;
        }

        return null;
    }
}
