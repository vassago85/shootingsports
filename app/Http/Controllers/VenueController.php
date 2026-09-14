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
            ->orderBy('name')
            ->get();

        return view('public.ranges.index', [
            'venues' => $venues,
            'province' => $province,
            'provinces' => Province::cases(),
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
            'jsonLd' => JsonLd::venue($venue),
        ]);
    }
}
