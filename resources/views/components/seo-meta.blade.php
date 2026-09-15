{{--
    Shared SEO + Open Graph + JSON-LD tags for public pages.

    Every non-home page passes a tighter title/description so search results and
    link previews don't all look identical. Pass jsonLd for page-specific
    structured data (Event, SportsOrganization, etc.) — the WebSite +
    SportsOrganization site graph below always renders.
--}}
@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'robots' => null,
    'image' => null,
    'type' => 'website',
    'jsonLd' => null,
])
@php
    use App\Support\JsonLd;

    $siteName = 'Shooting Sports';
    $tagline = 'Find your sport, your club, your match';
    $fullTitle = $title ? $title.' · '.$siteName : $siteName.' — '.$tagline;
    $description = $description
        ?: 'Find your sport. Find your club. Find your match. Every discipline, every province, every South African shooting match on one calendar.';
    $canonical = $canonical ?: url()->current();
    $image = $image ?: asset('images/og-default.png');
    $verification = config('services.google.site_verification');
    $umamiScriptUrl = config('services.umami.script_url');
    $umamiWebsiteId = config('services.umami.website_id');
    $siteGraph = JsonLd::site();
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
@if ($robots)
    <meta name="robots" content="{{ $robots }}">
@endif
<link rel="canonical" href="{{ $canonical }}">
<link rel="alternate" type="application/xml" title="Sitemap" href="{{ url('/sitemap.xml') }}">

@if ($verification)
    <meta name="google-site-verification" content="{{ $verification }}">
@endif

<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:locale" content="en_ZA">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">

<script type="application/ld+json">{!! json_encode($siteGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

@if ($jsonLd)
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif

@if ($umamiScriptUrl && $umamiWebsiteId)
    <script defer src="{{ $umamiScriptUrl }}" data-website-id="{{ $umamiWebsiteId }}"></script>
@endif
