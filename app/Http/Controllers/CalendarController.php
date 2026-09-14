<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('public.calendar', [
            'discipline' => $request->string('discipline')->toString() ?: null,
            'province' => $request->string('province')->toString() ?: null,
            'radius' => $request->string('radius')->toString() ?: null,
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'family' => $request->string('family')->toString() ?: 'all',
            'novice' => $request->boolean('novice'),
            'confirmed' => $request->boolean('confirmed'),
        ]);
    }
}
