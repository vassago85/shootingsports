@props(['page'])

@php
    $url = \App\Support\PageHeroes::url($page);
    $image = filled($url) ? "--hero-image: url('".str_replace(["\\", "'"], ['/', ''], $url)."')" : null;
    $style = collect([$attributes->get('style'), $image])->filter()->implode('; ');
@endphp

<section {{ $attributes->except('style')->class(['has-photo' => filled($url)])->merge(['style' => $style !== '' ? $style : false]) }}>
    {{ $slot }}
</section>
