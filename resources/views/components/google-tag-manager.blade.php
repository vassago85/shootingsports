@props([
    'part' => 'head',
])
@php
    $id = config('services.google.tag_manager_id');
@endphp
@if (is_string($id) && $id !== '')
    @if ($part === 'body')
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $id }}" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
    @else
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
        </script>
        <script async src="https://www.googletagmanager.com/gtm.js?id={{ $id }}"></script>
    @endif
@endif
