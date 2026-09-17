<?php

use App\Http\Middleware\CanonicalHost;
use Illuminate\Http\Request;

it('redirects trailing-slash public URLs to the bare path', function () {
    config(['app.url' => 'https://shootingsports.co.za']);

    $request = Request::create('https://shootingsports.co.za/calendar/', 'GET');
    $middleware = new CanonicalHost;

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe('https://shootingsports.co.za/calendar');
});

it('redirects www host to the APP_URL apex', function () {
    config(['app.url' => 'https://shootingsports.co.za']);

    $request = Request::create('https://www.shootingsports.co.za/calendar', 'GET');
    $middleware = new CanonicalHost;

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(301)
        ->and($response->headers->get('Location'))->toBe('https://shootingsports.co.za/calendar');
});

it('leaves unrelated hosts alone', function () {
    config(['app.url' => 'https://shootingsports.co.za']);

    $request = Request::create('http://shootingsports.test/calendar', 'GET');
    $middleware = new CanonicalHost;

    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('ok');
});
