<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Models\Organisation;
use App\Queries\PublicEventQuery;
use App\Support\JsonLd;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganisationController extends Controller
{
    public function index(Request $request): View
    {
        $province = $request->string('province')->toString()
            ? Province::fromUrlSlug($request->string('province')->toString())
            : null;

        $clubs = Organisation::query()
            ->published()
            ->clubs()
            ->with('disciplines')
            ->when($province, fn ($q) => $q->where('province', $province))
            ->orderBy('name')
            ->get();

        return view('public.clubs.index', [
            'clubs' => $clubs,
            'province' => $province,
            'provinces' => Province::cases(),
        ]);
    }

    public function show(Organisation $organisation): View
    {
        $this->assertPublished($organisation);
        abort_if($organisation->isFederationListing(), 404);

        $organisation->load('disciplines');

        $events = (new PublicEventQuery(organisationId: $organisation->id))->get();

        return view('public.clubs.show', [
            'organisation' => $organisation,
            'events' => $events,
            'jsonLd' => JsonLd::organisation($organisation),
        ]);
    }

    public function federation(Organisation $organisation): View
    {
        $this->assertPublished($organisation);
        abort_unless($organisation->isFederationListing(), 404);

        $organisation->load(['disciplines', 'federatedDisciplines', 'children']);

        $events = (new PublicEventQuery(organisationId: $organisation->id))->get();

        return view('public.federations.show', [
            'organisation' => $organisation,
            'events' => $events,
            'jsonLd' => JsonLd::organisation($organisation),
        ]);
    }

    private function assertPublished(Organisation $organisation): void
    {
        abort_unless($organisation->status === ListingStatus::Published, 404);
    }
}
