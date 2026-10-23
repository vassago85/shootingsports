<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrganisationResource;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public organisations directory for the mobile Find screen's Clubs
 * mode. The `scopeClubs()` helper on Organisation covers clubs,
 * associations, provincial bodies and series — the four
 * membership-adjacent types a shooter cares about on the Clubs tab.
 * Federations are intentionally excluded here; they live in their
 * own navigation space on the web and don't fit the "where do I go
 * shoot" question this list answers.
 */
class OrganisationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $province = $request->string('province')->toString() !== ''
            ? Province::fromUrlSlug($request->string('province')->toString())
                ?? Province::tryFrom($request->string('province')->toString())
            : null;

        $search = trim($request->string('q')->toString());

        $visitors = $request->boolean('visitors');

        $type = $request->string('type')->toString() !== ''
            ? OrganisationType::tryFrom($request->string('type')->toString())
            : null;

        $query = Organisation::query()
            ->published()
            ->with('disciplines')
            ->when($province, fn ($q) => $q->where('province', $province))
            ->when($visitors, fn ($q) => $q->where('visitors_welcome', true))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('town', 'like', $like);
                });
            });

        if ($type !== null) {
            $query->where('type', $type);
        } else {
            // Default view is the "clubs" family — matches the website
            // /clubs page. Pass `?type=federation` or similar to broaden.
            $query->clubs();
        }

        $organisations = $query->orderBy('name')->get();

        return OrganisationResource::collection($organisations);
    }
}
