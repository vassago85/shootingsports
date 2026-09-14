<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\Discipline;
use App\Models\Organisation;
use App\Queries\PublicEventQuery;
use App\Support\IcalFeed;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class IcalController extends Controller
{
    public function organisation(Organisation $organisation): Response
    {
        abort_unless($organisation->status === ListingStatus::Published, 404);

        $events = (new PublicEventQuery(organisationId: $organisation->id))->get();

        return $this->feed($organisation->name, $events);
    }

    public function discipline(Discipline $discipline): Response
    {
        abort_unless($discipline->is_published, 404);

        $events = (new PublicEventQuery(disciplineSlug: $discipline->slug))->get();

        return $this->feed($discipline->name.' matches', $events);
    }

    /**
     * @param  iterable<\App\Models\Event>  $events
     */
    private function feed(string $name, iterable $events): Response
    {
        return response(IcalFeed::build($name, $events), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.Str::slug($name).'.ics"',
        ]);
    }
}
