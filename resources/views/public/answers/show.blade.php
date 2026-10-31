<x-layouts.public
    :title="$title"
    :description="$description"
    :canonical="$canonical"
    :robots="$robots"
    :json-ld="$jsonLd"
>
    <main id="main">
        <div class="wrap dir-page">
            <x-dir-hero page="matches" kicker="South Africa" :title="$title">
                {{ $paragraphs[0] ?? '' }}
            </x-dir-hero>

            @foreach (array_slice($paragraphs, 1) as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach

            @foreach ($groups as $group)
                <section>
                    <h2>{{ $group['label'] }}</h2>
                    <ul>
                        @foreach ($group['events'] as $event)
                            <li>
                                <a href="{{ $event['url'] }}">{{ $event['title'] }}</a>
                                @if ($event['summary'] !== '')
                                    — {{ $event['summary'] }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            @if ($later !== [])
                <section>
                    <h2>Next upcoming events</h2>
                    <ul>
                        @foreach ($later as $event)
                            <li>
                                <a href="{{ $event['url'] }}">{{ $event['title'] }}</a>
                                @if ($event['summary'] !== '')
                                    — {{ $event['summary'] }}
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </main>
</x-layouts.public>
