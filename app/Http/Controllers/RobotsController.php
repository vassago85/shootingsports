<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * Paths that are sign-in, account, or operator surfaces. One
     * User-agent: * group covers Googlebot, Bingbot and the AI
     * fetchers. They are not named, and they are not blocked from /.
     *
     * @var list<string>
     */
    private const PRIVATE_PREFIXES = [
        '/admin',
        '/desk',
        '/me',
        '/my-memberships',
        '/login',
        '/register',
        '/email',
        '/upgrade',
        '/events/submit',
        '/suppliers/onboard',
        '/suppliers/start',
        '/paystack',
        '/enquiries/thread',
        '/enquiries/thanks',
        '/coming-soon',
        '/mockups',
    ];

    public function __invoke(): Response
    {
        $lines = ['User-agent: *'];

        if (config('seo.indexable')) {
            $lines[] = 'Allow: /';

            foreach (self::PRIVATE_PREFIXES as $prefix) {
                $lines[] = 'Disallow: '.$prefix;
            }
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
