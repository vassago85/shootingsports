<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

{{-- PHP's short_open_tag can be On in production containers, which
     would cause a literal "<?xml ..." in the compiled Blade cache to
     be parsed as an opening PHP tag → syntax error at runtime. Emitting
     the XML declaration through <?= (short-echo, always enabled since
     PHP 5.4) makes this template safe under any php.ini. --}}
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($sitemaps as $loc)
    <sitemap>
        <loc>{{ $loc }}</loc>
    </sitemap>
@endforeach
</sitemapindex>
