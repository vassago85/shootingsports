<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (config('seo.indexable')) {
            $lines[] = 'Allow: /';
        } else {
            $lines[] = 'Disallow: /';
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.url('/sitemap.xml');
        $lines[] = '';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
