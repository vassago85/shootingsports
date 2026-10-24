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
 * mode. Default scope is `scopeDiscoverable()`, which covers every
 * membership-adjacent body a shooter needs to find on their phone:
 * clubs, series, associations, federations and provincial bodies.
 * Federations and null-province associations come back first so the
 * client can render a "National" group above the province groups.
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
            ->with('disciplines.parent')
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
            // Default view includes federations and associations on
            // top of the clubs scope, so bodies like SAPRF, CTSASA
            // and NRL Hunter SA surface in the National group at the
            // top of the Clubs tab. Pass `?type=federation` (etc.)
            // to narrow when a future screen needs it.
            $query->discoverable();
        }

        // Primary sort by group so the client-side section list
        // renders National bodies first without having to re-sort.
        // Federations and province-less associations rank 0; every
        // other row ranks 1.
        $federationValue = OrganisationType::Federation->value;
        $associationValue = OrganisationType::Association->value;

        $organisations = $query
            ->orderByRaw(
                'CASE WHEN type = ? OR (type = ? AND province IS NULL) THEN 0 ELSE 1 END',
                [$federationValue, $associationValue],
            )
            ->orderBy('name')
            ->get();

        return OrganisationResource::collection($organisations);
    }
}
