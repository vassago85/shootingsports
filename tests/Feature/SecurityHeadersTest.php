<?php

beforeEach(function (): void {
    $this->withoutVite();
});

it('sends clickjacking and report-only csp headers on public pages', function () {
    $response = $this->get('/')
        ->assertOk()
        ->assertHeader('x-frame-options', 'SAMEORIGIN')
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader('referrer-policy', 'strict-origin-when-cross-origin')
        ->assertHeader('permissions-policy', 'geolocation=(), camera=(), microphone=(), payment=()')
        ->assertHeader('content-security-policy-report-only');

    $csp = (string) $response->headers->get('content-security-policy-report-only');

    expect($csp)
        ->toContain("frame-src 'self'")
        ->toContain('https://unpkg.com');
});

it('does not send hsts over http', function () {
    $this->get('/')->assertHeaderMissing('strict-transport-security');
});

it('sends hsts over https without preload', function () {
    $this->get('https://shootingsports.test/')
        ->assertHeader('strict-transport-security', 'max-age=31536000; includeSubDomains');
});

it('404s the health endpoint from a public ip', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->get('/up')
        ->assertNotFound();
});

it('serves the health endpoint from loopback', function () {
    $this->get('/up')->assertOk();
});

it('applies sitewide noindex when the site is not indexable', function () {
    config()->set('seo.indexable', false);

    $this->get('/')
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('leaves public pages indexable when the seo flag is on', function () {
    config()->set('seo.indexable', true);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('<meta name="robots" content="noindex, nofollow">', false);
});

it('publishes a security.txt', function () {
    $path = public_path('.well-known/security.txt');

    expect(is_file($path))->toBeTrue();

    $body = (string) file_get_contents($path);

    expect($body)
        ->toContain('Contact: mailto:hello@shootingsports.co.za')
        ->toContain('Canonical: https://shootingsports.co.za/.well-known/security.txt');
});

it('does not expose an admin registration page', function () {
    $this->get('/admin/register')->assertNotFound();
});
