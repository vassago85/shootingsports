@if ($kit)
    <h2 class="app-sub">What to pack</h2>
    <p class="app-lead">These basics come with {{ $kit['name'] }}. When you pack for a match, start here and add what that day needs.</p>
    @if ($kit['items'] !== [])
        <div class="app-checks">
            @foreach ($kit['items'] as $item)
                <div class="app-check">
                    <span class="app-box" aria-hidden="true"></span>
                    <span>{{ $item['label'] }}</span>
                </div>
            @endforeach
        </div>
    @else
        <p class="app-empty">No starter list for this sport yet.</p>
    @endif
    @if ($kit['matches']->isNotEmpty())
        <div class="app-stack">
            @foreach ($kit['matches'] as $row)
                <a class="app-btn secondary" href="{{ $app('pack', ['match' => $row['slug']]) }}">Pack for {{ $row['title'] }}</a>
            @endforeach
        </div>
    @endif
@endif
