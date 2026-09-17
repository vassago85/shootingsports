<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DisciplineResource;
use App\Models\Discipline;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DisciplineController extends Controller
{
    /**
     * Top-level (parent-null) disciplines with upcoming counts, used
     * by the mobile "Discover" screen and filter chip source of truth.
     */
    public function index(): AnonymousResourceCollection
    {
        $upcoming = Discipline::upcomingCounts();

        $disciplines = Discipline::query()
            ->where('is_published', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->each(function (Discipline $discipline) use ($upcoming): void {
                $discipline->setAttribute('events_count', $upcoming[$discipline->id] ?? 0);
            });

        return DisciplineResource::collection($disciplines);
    }
}
