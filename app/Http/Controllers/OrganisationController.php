<?php

namespace App\Http\Controllers;

use App\Enums\Division;
use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Models\Organisation;
use App\Queries\PublicEventQuery;
use App\Services\Discovery\DiscoveryStats;
use App\Support\JsonLd;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrganisationController extends Controller
{
    public function __construct(private readonly DiscoveryStats $stats) {}

    public function index(Request $request): View
    {
        $province = $request->string('province')->toString()
            ? Province::fromUrlSlug($request->string('province')->toString())
            : null;
        $division = Division::fromPublicQuery($request->string('division')->toString());

        $clubs = Organisation::query()
            ->published()
            ->clubs()
            ->with('disciplines')
            ->when($province, fn ($q) => $q->where('province', $province))
            ->when($division, fn ($q) => $q->inDivision($division))
            ->orderBy('name')
            ->get();

        $countPhrase = $this->countPhrase($clubs->count(), 'club', 'clubs');
        $seoTitle = $province ? 'Shooting clubs in '.$province->getLabel() : 'Shooting clubs & series in South Africa';
        $seoDescription = $province
            ? 'Shooting clubs, ranges and series in '.$province->getLabel().'. '.$countPhrase.' on the South African register.'
            : $countPhrase.'. Membership clubs, associations and branded match series across South Africa.';

        $itemList = JsonLd::itemList(
            $clubs->map(fn (Organisation $club): array => [
                'name' => $club->name,
                'url' => $club->isFederationListing()
                    ? route('federations.show', $club->slug)
                    : route('clubs.show', $club->slug),
                'id' => $club->schemaId(),
            ])->all(),
            $seoTitle,
        );

        $breadcrumbs = JsonLd::breadcrumbs([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Clubs & series', 'url' => route('clubs.index')],
        ]);

        return view('public.clubs.index', [
            'clubs' => $clubs,
            'province' => $province,
            'division' => $division,
            'provinces' => Province::cases(),
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'jsonLd' => [$itemList, $breadcrumbs],
        ]);
    }

    private function countPhrase(int $count, string $singular, string $plural): string
    {
        return match ($count) {
            0 => 'No listings yet',
            1 => '1 '.$singular,
            default => $count.' '.$plural,
        };
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
            'seo' => Seo::forOrganisation($organisation),
            // Disciplines derived from event history + explicit pivot,
            // so a club that has run three PRS matches shows the PRS
            // tag whether or not staff attached it manually.
            'inferredDisciplines' => $this->stats->inferredDisciplinesForOrganisation($organisation),
            'commonRanges' => $this->stats->commonRangesForOrganisation($organisation),
            'jsonLd' => [
                JsonLd::organisation($organisation),
                JsonLd::breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Clubs & series', 'url' => route('clubs.index')],
                    ['name' => $organisation->name, 'url' => route('clubs.show', $organisation->slug)],
                ]),
            ],
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
            'seo' => Seo::forOrganisation($organisation),
            'inferredDisciplines' => $this->stats->inferredDisciplinesForOrganisation($organisation),
            'commonRanges' => $this->stats->commonRangesForOrganisation($organisation),
            'jsonLd' => [
                JsonLd::organisation($organisation),
                JsonLd::breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Clubs & series', 'url' => route('clubs.index')],
                    ['name' => $organisation->name, 'url' => route('federations.show', $organisation->slug)],
                ]),
            ],
        ]);
    }

    private function assertPublished(Organisation $organisation): void
    {
        abort_unless($organisation->status === ListingStatus::Published, 404);
    }
}
