<?php

/**
 * The previous shootingsports.co.za ran on WordPress. Its URLs are
 * still indexed and need either a clean 301 to the current
 * equivalent or a 410 Gone so crawlers drop them. These tests pin
 * the map in `RedirectLegacyWordPressUrls` and prove the sitemap
 * never leaks any of the retired paths back into the index.
 */

use function Pest\Laravel\get;

it('redirects /sample-page to home with 301, slash-insensitive', function (string $url): void {
    get($url)
        ->assertStatus(301)
        ->assertRedirect('/');
})->with(['/sample-page', '/sample-page/']);

it('redirects /new-user-register to the current register route with 301, slash-insensitive', function (string $url): void {
    get($url)
        ->assertStatus(301)
        ->assertRedirect(route('register', absolute: false));
})->with(['/new-user-register', '/new-user-register/']);

it('redirects /members-portal to the current login route with 301, slash-insensitive', function (string $url): void {
    get($url)
        ->assertStatus(301)
        ->assertRedirect(route('login', absolute: false));
})->with(['/members-portal', '/members-portal/']);

it('returns 410 for wp-admin and nested wp-admin paths, slash-insensitive', function (string $url): void {
    get($url)->assertStatus(410);
})->with([
    '/wp-admin',
    '/wp-admin/',
    '/wp-admin/plugins.php',
    '/wp-admin/admin-ajax.php',
]);

it('returns 410 for /wp-login.php', function (): void {
    get('/wp-login.php')->assertStatus(410);
});

it('returns 410 for WordPress plain-permalink queries on root', function (string $url): void {
    get($url)->assertStatus(410);
})->with([
    '/?p=1',
    '/?p=1234',
    '/?page_id=42',
]);

it('still serves the home page when root has no WordPress permalink query', function (): void {
    get('/?foo=bar')->assertOk();
});

it('returns 410 for /category and any nested category archive, slash-insensitive', function (string $url): void {
    get($url)->assertStatus(410);
})->with([
    '/category',
    '/category/',
    '/category/news',
    '/category/news/',
    '/category/news/2024/',
]);

it('returns 410 for /tag and any nested tag archive, slash-insensitive', function (string $url): void {
    get($url)->assertStatus(410);
})->with([
    '/tag',
    '/tag/',
    '/tag/hunting',
    '/tag/hunting/',
]);

it('410 responses are marked noindex for crawlers', function (): void {
    $response = get('/wp-admin');

    $response->assertStatus(410);
    expect($response->headers->get('X-Robots-Tag'))->toBe('noindex');
});

it('does not interfere with non-GET requests', function (): void {
    // POST to a legacy path falls through to Laravel's normal routing
    // (404 since no route defines POST /wp-admin). The important bit
    // is that the middleware does NOT return 410 for a non-GET — form
    // posts to real routes elsewhere must not be swallowed.
    $response = $this->post('/wp-admin');

    expect($response->status())->not->toBe(410);
});

it('leaves the current /feed route serving its real feed, not 410', function (): void {
    // /feed is a live article feed page on the new site, not a WP
    // leftover. If someone ever moves it into the GONE list, this
    // test will scream.
    get('/feed')->assertOk();
});

it('keeps the live /register and /login routes intact after the WP map runs', function (): void {
    get('/register')->assertOk();
    get('/login')->assertOk();
});

it('does not emit any legacy WordPress paths into the sitemap index', function (): void {
    $xml = get('/sitemap.xml')->assertOk()->getContent();

    foreach ([
        '/sample-page',
        '/new-user-register',
        '/members-portal',
        '/wp-admin',
        '/wp-login.php',
        '/category/',
        '/tag/',
        '?p=',
    ] as $needle) {
        expect(str_contains($xml, $needle))
            ->toBeFalse("sitemap index must not contain '{$needle}'");
    }
});

it('does not emit any legacy WordPress paths into the pages sitemap', function (): void {
    $xml = get('/sitemaps/pages.xml')->assertOk()->getContent();

    foreach ([
        '/sample-page',
        '/new-user-register',
        '/members-portal',
        '/wp-admin',
        '/wp-login.php',
        '/category/',
        '/tag/',
    ] as $needle) {
        expect(str_contains($xml, $needle))
            ->toBeFalse("pages sitemap must not contain '{$needle}'");
    }
});
