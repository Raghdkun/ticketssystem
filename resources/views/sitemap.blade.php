<?php echo '<?xml version="1.0" encoding="UTF-8"?>'."\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@isset($url['lastmod'])
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endisset
        <changefreq>{{ $url['changefreq'] }}</changefreq>
        <priority>{{ $url['priority'] }}</priority>
        {{-- Both locales are the same URL with a ?lang switch, declared so
             search engines index the right one per audience. --}}
        <xhtml:link rel="alternate" hreflang="ar" href="{{ $url['loc'] }}?lang=ar"/>
        <xhtml:link rel="alternate" hreflang="en" href="{{ $url['loc'] }}?lang=en"/>
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ $url['loc'] }}"/>
    </url>
@endforeach
</urlset>
