@props([
    // Rows produced by Organisation::socialLinks() / Event::socialLinks()
    // via App\Support\SocialLinks. Shape:
    //   list<array{key: 'website'|'facebook'|'instagram'|'whatsapp', label: string, url: string}>
    'links' => [],
    // Short caption shown above the icon row, e.g. "Follow this club".
    // Pass null to omit.
    'label' => null,
])

@if (! empty($links))
    @php
        /** @var array<string, string> $icons */
        $icons = [
            'website' => '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/></svg>',
            'facebook' => '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 22v-8h2.7l.4-3.2h-3.1V8.7c0-.9.3-1.6 1.6-1.6h1.7V4.2c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.2v2.5H7.4V14h2.6v8h3.5z"/></svg>',
            'instagram' => '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>',
            'whatsapp' => '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12.04 2.4c-5.3 0-9.6 4.3-9.6 9.6 0 1.7.4 3.3 1.3 4.8L2.4 21.6l4.9-1.3c1.4.8 3 1.2 4.7 1.2 5.3 0 9.6-4.3 9.6-9.6S17.34 2.4 12.04 2.4Zm0 17.5c-1.5 0-3-.4-4.3-1.1l-.3-.2-2.9.8.8-2.8-.2-.3c-.8-1.3-1.2-2.8-1.2-4.3 0-4.4 3.6-7.9 7.9-7.9 2.1 0 4.1.8 5.6 2.3s2.3 3.5 2.3 5.6c0 4.4-3.6 7.9-7.7 7.9Zm4.5-5.9c-.2-.1-1.4-.7-1.6-.8-.2-.1-.4-.1-.6.1s-.6.8-.8 1c-.1.2-.3.2-.5.1-.2-.1-1-.4-1.9-1.2-.7-.6-1.2-1.4-1.3-1.6-.1-.2 0-.4.1-.5l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.3 0-.5s-.6-1.4-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.1 3c.1.2 2 3.1 4.8 4.2 2.3.9 2.8.7 3.3.7.5-.1 1.6-.6 1.8-1.3.2-.6.2-1.2.2-1.3-.1-.1-.2-.2-.4-.3Z"/></svg>',
        ];
    @endphp

    <div {{ $attributes->class(['social-links']) }} role="group" @if ($label) aria-label="{{ $label }}" @endif>
        @if ($label)
            <p class="social-links-label">{{ $label }}</p>
        @endif
        <ul class="social-links-row">
            @foreach ($links as $link)
                <li>
                    <a
                        href="{{ $link['url'] }}"
                        rel="noopener noreferrer"
                        target="_blank"
                        class="social-link social-link--{{ $link['key'] }}"
                        aria-label="{{ $link['label'] }} (opens in a new tab)"
                        title="{{ $link['label'] }}"
                    >
                        <span class="social-link-icon" aria-hidden="true">{!! $icons[$link['key']] !!}</span>
                        <span class="social-link-text">{{ $link['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </div>
@endif
