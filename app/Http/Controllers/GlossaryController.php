<?php

namespace App\Http\Controllers;

use App\Support\Glossary;
use App\Support\JsonLd;
use App\Support\Seo;
use Illuminate\View\View;

class GlossaryController extends Controller
{
    public function __invoke(): View
    {
        $disciplines = Glossary::disciplines();

        return view('public.glossary', [
            'disciplines' => $disciplines,
            'seo' => new Seo(
                title: 'Event type glossary',
                description: Seo::clip('Every shooting event type on the register, with the names it is also called and a short description.'),
                canonical: route('glossary'),
            ),
            'jsonLd' => [
                Glossary::jsonLd($disciplines),
                JsonLd::breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Discover', 'url' => route('disciplines.index')],
                    ['name' => 'Glossary', 'url' => route('glossary')],
                ]),
            ],
        ]);
    }
}
