<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Models\Venue;
use App\Queries\PublicEventQuery;
use App\Support\JsonLd;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function index(Request $request): View
    {
        $province = $request->string('province')->toString()
            ? Province::fromUrlSlug($request->string('province')->toString())
            : null;

        $venues = Venue::query()
            ->published()
            ->with('disciplines')
            ->when($province, fn ($q) => $q->where('province', $province))
            ->orderByTier()
            ->get();

        $countPhrase = match ($venues->count()) {
            0 => 'No ranges listed yet',
            1 => '1 range',
            default => $venues->count().' ranges',
        };

        $seoTitle = $province ? 'Shooting ranges in '.$province->getLabel() : 'Shooting ranges in South Africa';
        $seoDescription = $province
            ? 'Shooting ranges and venues in '.$province->getLabel().' — '.$countPhrase.' on the South African register.'
            : $countPhrase.' across South Africa — the venues where matches actually happen.';

        $itemList = JsonLd::itemList(
            $venues->map(fn (Venue $v): array => [
                'name' => $v->name,
                'url' => route('ranges.show', $v->slug),
            ])->all(),
            $seoTitle,
        );

        $breadcrumbs = JsonLd::breadcrumbs([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Ranges', 'url' => route('ranges.index')],
        ]);

        return view('public.ranges.index', [
            'venues' => $venues,
            'province' => $province,
            'provinces' => Province::cases(),
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'jsonLd' => [$itemList, $breadcrumbs],
        ]);
    }

    public function show(Venue $venue): View
    {
        abort_unless($venue->status === ListingStatus::Published, 404);

        $venue->load('disciplines');

        $events = (new PublicEventQuery(venueId: $venue->id))->get();

        return view('public.ranges.show', [
            'venue' => $venue,
            'events' => $events,
            'jsonLd' => [
                JsonLd::venue($venue),
                JsonLd::breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Ranges', 'url' => route('ranges.index')],
                    ['name' => $venue->name, 'url' => route('ranges.show', $venue->slug)],
                ]),
            ],
        ]);
    }
}
