@props(['active' => 'dashboard', 'title' => 'Admin'])
@php
    $role = request('role') === 'director' ? 'director' : 'staff';
    $items = [
        ['group' => 'Overview', 'label' => 'Dashboard', 'route' => 'mockups.admin.dashboard', 'key' => 'dashboard', 'roles' => ['staff', 'director']],
        ['group' => 'Events', 'label' => 'Matches', 'route' => 'mockups.admin.matches', 'key' => 'matches', 'roles' => ['staff', 'director']],
        ['group' => 'Events', 'label' => 'Calendar', 'route' => 'mockups.matches.calendar', 'key' => 'calendar', 'roles' => ['staff', 'director']],
        ['group' => 'Directory', 'label' => 'Clubs', 'route' => 'mockups.admin.clubs', 'key' => 'clubs', 'roles' => ['staff', 'director']],
        ['group' => 'Directory', 'label' => 'Ranges', 'route' => 'mockups.admin.ranges', 'key' => 'ranges', 'roles' => ['staff']],
        ['group' => 'Directory', 'label' => 'Sports', 'route' => 'mockups.admin.sports', 'key' => 'sports', 'roles' => ['staff']],
        ['group' => 'Directory', 'label' => 'Industry', 'route' => 'mockups.admin.industry', 'key' => 'industry', 'roles' => ['staff']],
        ['group' => 'Community', 'label' => 'Submissions', 'route' => 'mockups.admin.submissions', 'key' => 'submissions', 'roles' => ['staff']],
        ['group' => 'Quality', 'label' => 'Needs attention', 'route' => 'mockups.admin.quality', 'key' => 'quality', 'roles' => ['staff', 'director']],
        ['group' => 'Quality', 'label' => 'Duplicates', 'route' => 'mockups.admin.duplicates', 'key' => 'duplicates', 'roles' => ['staff']],
        ['group' => 'Commercial', 'label' => 'Advertising', 'route' => 'mockups.admin.advertising', 'key' => 'advertising', 'roles' => ['staff']],
        ['group' => 'System', 'label' => 'Reports', 'route' => 'mockups.admin.reports', 'key' => 'reports', 'roles' => ['staff']],
    ];
    $visible = array_values(array_filter($items, fn (array $item): bool => in_array($role, $item['roles'], true)));
@endphp
<!DOCTYPE html>
<html lang="en-ZA" @class(['mk-device-mobile' => request('device') === 'mobile'])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title ?? 'Admin' }} — ShootingSports admin mockup</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira+Condensed:wght@500;600;700&family=IBM+Plex+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>{!! file_get_contents(resource_path('css/mockups.css')) !!}</style>
</head>
<body>
    <div class="mk-ribbon">
        <span>Admin mockup</span>
        <a href="{{ $mk('mockups.index', [], false) }}">Index</a>
        <a href="{{ $mk('mockups.home') }}">Public site</a>
        <span class="sp">
            <a href="{{ $mk(request()->route()->getName(), array_merge(request()->route()->parameters(), ['role' => $role]), false) }}">Desktop</a>
            <a href="{{ $mk(request()->route()->getName(), array_merge(request()->route()->parameters(), request()->except('device'), ['device' => 'mobile', 'role' => $role]), false) }}">Mobile</a>
        </span>
    </div>
    <div class="mk-shell-admin">
        <aside class="mk-sidenav" aria-label="Admin">
            <a class="brand" href="{{ $mk('mockups.admin.dashboard', ['role' => $role]) }}" style="margin-bottom:8px">
                <span class="brand-text">
                    <span class="wordmark">ShootingSports</span>
                    <span class="sub">Admin</span>
                </span>
            </a>
            @include('mockups.partials.admin-nav', ['visible' => $visible, 'active' => $active, 'role' => $role])
        </aside>
        <div class="mk-admin-main">
            <div class="mk-admin-top">
                <p class="label">Operations</p>
                <div class="mk-role">
                    Viewing as
                    <a href="{{ request()->fullUrlWithQuery(['role' => 'staff']) }}" @class(['on' => $role === 'staff'])>Staff</a>
                    <a href="{{ request()->fullUrlWithQuery(['role' => 'director']) }}" @class(['on' => $role === 'director'])>Match director</a>
                </div>
            </div>
            <details class="mk-nav-toggle">
                <summary class="btn ghost">Menu</summary>
                <div class="mk-sidenav-in">
                    @include('mockups.partials.admin-nav', ['visible' => $visible, 'active' => $active, 'role' => $role])
                </div>
            </details>
            {{ $slot }}
        </div>
    </div>
</body>
</html>
