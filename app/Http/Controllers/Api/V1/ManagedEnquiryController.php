<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Light enquiries surface — list the inbox items a desk user holds
 * against any of their managed listings (clubs, shops, or ranges).
 * The reply endpoint posts a single plain-text message onto the
 * existing EnquiryReply chain so the mobile user doesn't have to
 * jump out to the web just to say "yes, we have space".
 */
class ManagedEnquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $rows = $this->scopedQuery($user)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'about_type', 'about_id', 'name', 'email', 'subject', 'body', 'status', 'read_at', 'created_at'])
            ->map(fn (Enquiry $enquiry): array => [
                'id' => $enquiry->id,
                'about_type' => $enquiry->about_type,
                'about_id' => $enquiry->about_id,
                'from_name' => $enquiry->name,
                'from_email' => $enquiry->email,
                'subject' => $enquiry->subject,
                'preview' => Str::limit((string) $enquiry->body, 200),
                'status' => $enquiry->status?->value,
                'read_at' => $enquiry->read_at?->toIso8601String(),
                'received_at' => $enquiry->created_at?->toIso8601String(),
            ])
            ->all();

        return response()->json(['data' => $rows]);
    }

    public function markRead(Request $request, int $enquiryId): JsonResponse
    {
        $user = $request->user();

        $enquiry = $this->scopedQuery($user)->whereKey($enquiryId)->firstOrFail();
        $enquiry->markRead();

        return response()->json([
            'id' => $enquiry->id,
            'status' => $enquiry->fresh()->status?->value,
        ]);
    }

    /**
     * Enquiries about any listing the current user manages. The pivot
     * tables are the source of truth; `claimed_by` is a legacy fallback
     * for pre-pivot shops / ranges and is layered in with `orWhere`
     * clauses rather than relied on exclusively. `about_type` on the
     * row is the morph-map alias ("organisation", "provider",
     * "venue") thanks to Relation::enforceMorphMap in AppServiceProvider.
     */
    private function scopedQuery(User $user): Builder
    {
        $orgIds = $user->managedOrganisations()->pluck('organisations.id');
        $providerIds = $user->managedProviders()->pluck('providers.id')
            ->merge($user->providers()->pluck('id'))
            ->unique();
        $venueIds = $user->managedVenues()->pluck('venues.id');

        $morphOrg = Relation::getMorphAlias(Organisation::class);
        $morphProvider = Relation::getMorphAlias(Provider::class);
        $morphVenue = Relation::getMorphAlias(Venue::class);

        return Enquiry::query()
            ->where(function ($query) use ($orgIds, $providerIds, $venueIds, $morphOrg, $morphProvider, $morphVenue): void {
                $query
                    ->when(
                        $orgIds->isNotEmpty(),
                        fn ($q) => $q->orWhere(fn ($sub) => $sub
                            ->where('about_type', $morphOrg)
                            ->whereIn('about_id', $orgIds)),
                    )
                    ->when(
                        $providerIds->isNotEmpty(),
                        fn ($q) => $q->orWhere(fn ($sub) => $sub
                            ->where('about_type', $morphProvider)
                            ->whereIn('about_id', $providerIds)),
                    )
                    ->when(
                        $venueIds->isNotEmpty(),
                        fn ($q) => $q->orWhere(fn ($sub) => $sub
                            ->where('about_type', $morphVenue)
                            ->whereIn('about_id', $venueIds)),
                    );
            });
    }
}
