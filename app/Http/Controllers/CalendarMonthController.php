<?php

namespace App\Http\Controllers;

use App\Enums\Province;
use App\Models\Discipline;
use App\Support\JsonLd;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarMonthController extends Controller
{
    public function __invoke(Request $request): View
    {
        $disciplineSlug = $request->string('discipline')->toString() ?: null;
        $provinceSlug = $request->string('province')->toString() ?: null;
        $from = $request->string('from')->toString() ?: null;

        $discipline = $disciplineSlug
            ? Discipline::query()->where('slug', $disciplineSlug)->where('is_published', true)->first()
            : null;

        $province = $provinceSlug ? Province::fromUrlSlug($provinceSlug) : null;

        $month = $request->string('month')->toString() ?: null;

        if (! $month || ! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = filled($from)
                ? Carbon::parse($from)->format('Y-m')
                : now()->format('Y-m');
        }

        $crumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Calendar', 'url' => route('calendar')],
            ['name' => 'Month', 'url' => route('calendar.month')],
        ];

        return view('public.calendar-month', [
            'discipline' => $disciplineSlug,
            'province' => $provinceSlug,
            'radius' => $request->string('radius')->toString() ?: null,
            'near' => $request->string('near')->toString() ?: null,
            'lat' => $request->string('lat')->toString() ?: null,
            'lng' => $request->string('lng')->toString() ?: null,
            'from' => $from,
            'to' => $request->string('to')->toString() ?: null,
            'family' => $request->string('family')->toString() ?: 'all',
            'novice' => $request->boolean('novice'),
            'confirmed' => $request->boolean('confirmed'),
            'month' => $month,
            'seoTitle' => 'Shooting competitions calendar, month view',
            'seoDescription' => 'Upcoming South African shooting matches on a month grid. Filter by discipline, province, and distance.',
            'canonical' => route('calendar.month', array_filter([
                'discipline' => $discipline?->slug,
                'province' => $province?->urlSlug(),
            ])),
            'jsonLd' => [JsonLd::breadcrumbs($crumbs)],
        ]);
    }
}
