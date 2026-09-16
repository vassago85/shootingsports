<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>

{{-- See public/sitemaps/index.blade.php for why the XML declaration
     is emitted through a <?= short-echo tag instead of appearing as
     raw text — short_open_tag=On in the prod container turns raw
     "<?xml ..." into an unparseable PHP tag. --}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
        @isset($url['lastmod'])
            <lastmod>{{ $url['lastmod'] }}</lastmod>
        @endisset
    </url>
@endforeach
</urlset>
