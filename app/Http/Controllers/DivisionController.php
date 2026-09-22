<?php

namespace App\Http\Controllers;

use App\Enums\Division;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\View\View;

class DivisionController extends Controller
{
    public function show(string $division): View
    {
        $divisionEnum = Division::tryFrom($division);

        abort_unless($divisionEnum?->isPublic() === true, 404);

        $sports = Discipline::query()
            ->where('is_published', true)
            ->whereNull('parent_id')
            ->whereHas('divisionLinks', fn ($query) => $query->where('division', $divisionEnum))
            ->with('federation')
            ->orderBy('name')
            ->get();

        return view('public.divisions.show', [
            'division' => $divisionEnum,
            'sports' => $sports,
            'clubs' => Organisation::query()->published()->clubs()->inDivision($divisionEnum)->orderBy('name')->limit(8)->get(),
            'ranges' => Venue::query()->published()->inDivision($divisionEnum)->orderBy('name')->limit(8)->get(),
            'matches' => Event::query()->upcoming()->inDivision($divisionEnum)->orderBy('starts_at')->limit(6)->get(),
            'suppliers' => Provider::query()->published()->listed()->inDivision($divisionEnum)->orderBy('name')->limit(8)->get(),
        ]);
    }
}
