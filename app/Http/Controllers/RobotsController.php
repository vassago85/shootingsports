<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class RobotsController extends Controller
{
    /**
     * One group, many user-agents. `*` covers anything not named.
     * The named crawlers are listed so a host-level bot rule cannot
     * claim we never addressed them. Every agent gets Allow: / and
     * the same private-path disallows.
     *
     * @var list<string>
     */
    private const AGENTS = [
        '*',
        'Googlebot',
        'Bingbot',
        'Applebot',
        'ClaudeBot',
        'Claude-User',
        'Claude-SearchBot',
        'GPTBot',
        'OAI-SearchBot',
        'ChatGPT-User',
        'PerplexityBot',
        'Google-Extended',
    ];

    /**
     * Paths that are sign-in, account, or operator surfaces.
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
        $lines = [];

        foreach (self::AGENTS as $agent) {
            $lines[] = 'User-agent: '.$agent;
        }

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
