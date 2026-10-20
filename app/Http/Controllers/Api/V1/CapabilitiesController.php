<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\DeskCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Full capability-card payload for the mobile Manage hub. The brief
 * UserResource `capabilities` array already lists the kinds; this
 * endpoint adds names, lists, summaries, and the "open on the
 * website" URL per card so the app can render the hub without
 * re-deriving any of it client-side.
 */
class CapabilitiesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => DeskCapabilities::for($user),
        ]);
    }
}
