<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class StaticPageController extends Controller
{
    public function advertise(): View
    {
        return view('public.static.advertise');
    }

    public function claim(): View
    {
        return view('public.static.claim');
    }

    public function privacy(): View
    {
        return view('public.static.privacy');
    }

    public function terms(): View
    {
        return view('public.static.terms');
    }

    public function embedDocs(): View
    {
        return view('public.static.embed');
    }
}
