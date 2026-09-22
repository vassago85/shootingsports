@php
    $publicProfile = $sponsor['public'] ?? true;
    $href = $publicProfile
        ? $app('supplier', ['supplier' => $sponsor['slug']])
        : ($sponsor['website'] ?? null);
@endphp
@if ($href)
    <a class="app-sponsor" href="{{ $href }}" @if ($publicProfile) rel="sponsored" @else target="_blank" rel="sponsored noopener noreferrer" @endif>
@else
    <div class="app-sponsor">
@endif
    <span class="app-sponsor-label">Sponsored</span>
    <strong>{{ $sponsor['headline'] }}</strong>
    <em>{{ collect([$sponsor['headline'] !== $sponsor['name'] ? $sponsor['name'] : null, $sponsor['category'], $sponsor['place']])->filter()->implode(' · ') }}</em>
    @if ($sponsor['body'] !== '')
        <span class="app-sponsor-body">{{ \Illuminate\Support\Str::limit($sponsor['body'], 110) }}</span>
    @endif
@if ($href)
    </a>
@else
    </div>
@endif
