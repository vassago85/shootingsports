@php
    $slug = $club['slug'] ?? null;
    $params = $slug ? ['club' => $slug] : [];
    $items = [
        ['label' => 'Overview', 'route' => 'mockups.manage.index', 'key' => 'overview'],
        ['label' => 'Matches', 'route' => 'mockups.manage.matches', 'key' => 'matches'],
        ['label' => 'New match', 'route' => 'mockups.manage.matches.new', 'key' => 'new-match'],
        ['label' => 'Club profile', 'route' => 'mockups.manage.profile', 'key' => 'profile'],
    ];
@endphp
<nav class="mk-views" aria-label="Club desk">
    @foreach ($items as $item)
        <a href="{{ $mk($item['route'], $params) }}" @class(['on' => ($active ?? '') === $item['key']])>{{ $item['label'] }}</a>
    @endforeach
</nav>
