<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PlanLimitExceeded;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FollowResource;
use App\Models\Discipline;
use App\Models\Follow;
use App\Models\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Mobile-facing CRUD for the shooter's follows. The web Livewire
 * FollowButton still writes to the same `follows` table so a follow
 * created on either surface shows up on the other.
 *
 * The public API uses "club" as the type alias for Organisation
 * (what shooters call them) while the DB morph map stays on
 * "organisation" to match the model. The controller normalises
 * input and re-aliases output so neither side needs to know about
 * the other.
 */
class MeFollowController extends Controller
{
    /**
     * Followable kinds this API exposes. Venue follows exist in the
     * DB from the web FollowButton for ranges, but the mobile home
     * feed brief only cares about clubs (incl. federations) and
     * disciplines, so we deliberately do not expose venues here.
     */
    private const INPUT_TO_MORPH = [
        'club' => 'organisation',
        'organisation' => 'organisation',
        'discipline' => 'discipline',
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $follows = $request->user()
            ->follows()
            ->with('followable')
            ->whereIn('followable_type', array_values(self::INPUT_TO_MORPH))
            ->latest('id')
            ->get();

        return FollowResource::collection($follows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(self::INPUT_TO_MORPH))],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        $morphType = self::INPUT_TO_MORPH[$data['type']];
        $this->assertTargetExists($morphType, (int) $data['id']);

        $user = $request->user();

        $existing = Follow::query()
            ->where('user_id', $user->id)
            ->where('followable_type', $morphType)
            ->where('followable_id', $data['id'])
            ->first();

        if ($existing !== null) {
            $existing->load('followable');

            // Idempotent: same shape as a fresh create, 200 instead
            // of 201 so the client can tell the difference if it
            // cares (nothing in this app does).
            return FollowResource::make($existing)
                ->response()
                ->setStatusCode(200);
        }

        try {
            $follow = Follow::create([
                'user_id' => $user->id,
                'followable_type' => $morphType,
                'followable_id' => (int) $data['id'],
            ]);
        } catch (PlanLimitExceeded $e) {
            // 403 with the same trigger key the web UpgradePrompt
            // uses, so the mobile client can decide whether to show
            // a toast or a full upgrade sheet.
            return response()->json([
                'message' => 'Follow limit reached on your current plan.',
                'trigger' => $e->trigger,
                'limit' => $e->user->limit($e->limitKey),
                'current' => $e->current,
            ], 403);
        }

        $follow->load('followable');

        return FollowResource::make($follow)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, string $type, int $id): JsonResponse
    {
        if (! array_key_exists($type, self::INPUT_TO_MORPH)) {
            abort(404);
        }

        $morphType = self::INPUT_TO_MORPH[$type];

        $request->user()
            ->follows()
            ->where('followable_type', $morphType)
            ->where('followable_id', $id)
            ->delete();

        // Idempotent: 204 whether the row existed or not. The mobile
        // client's optimistic "Unfollow" tap should never fail just
        // because a stale list was shown.
        return response()->json(null, 204);
    }

    private function assertTargetExists(string $morphType, int $id): void
    {
        $exists = match ($morphType) {
            'organisation' => Organisation::query()->whereKey($id)->exists(),
            'discipline' => Discipline::query()->whereKey($id)->exists(),
            default => false,
        };

        abort_unless($exists, 404, 'Followable target not found.');
    }
}
