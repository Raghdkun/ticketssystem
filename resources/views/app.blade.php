<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- The page background before the stylesheet arrives. These are the
             --background tokens from app.css; anything else flashes the wrong
             colour on the very first paint, which on a phone in a dark venue
             is a white flash. --}}
        <style>
            html {
                background-color: #F6F1EA;
            }

            html.dark {
                background-color: #0D0E0F;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{-- The two Cairo subsets every page needs. Arabic pages still carry
             Latin digits and currency codes, so both are on the critical
             path; @vite only preloads its own chunks and CSS. --}}
        @foreach (['resources/fonts/cairo-arabic-wght-normal.woff2', 'resources/fonts/cairo-latin-wght-normal.woff2'] as $font)
            <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset($font) }}">
        @endforeach

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @php($platform = app(\App\Services\Settings::class))
        <x-inertia::head>
            {{-- The platform name is admin-editable and lives in the
                 database; APP_NAME is only the deployment's label. --}}
            <title>{{ $platform->appName() }}</title>
        </x-inertia::head>
        {{-- Link unfurlers never run JavaScript, so these are rendered here
             rather than from the page component's <Head>. --}}
        @php($og = $page['props']['og'] ?? [])
        <meta property="og:site_name" content="{{ $platform->appName() }}">
        <meta property="og:type" content="{{ $og['type'] ?? 'website' }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:title" content="{{ $og['title'] ?? $platform->appName() }}">
        @if (! empty($og['description']))
            <meta property="og:description" content="{{ $og['description'] }}">
            <meta name="description" content="{{ $og['description'] }}">
        @endif
        @if (! empty($og['image']))
            <meta property="og:image" content="{{ $og['image'] }}">
            @if (! empty($og['width']))
                <meta property="og:image:width" content="{{ $og['width'] }}">
                <meta property="og:image:height" content="{{ $og['height'] }}">
            @endif
        @else
            {{-- A page with no cover of its own unfurls as the brand card. --}}
            <meta property="og:image" content="{{ url('/og-default.png') }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
        @endif
        <meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_SY' : 'en_GB' }}">
        <meta name="twitter:card" content="summary_large_image">

        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#F6F1EA" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0D0E0F" media="(prefers-color-scheme: dark)">
        {{-- The standard name; Apple's prefixed one stays for older iOS,
             which is the platform that most needs the home-screen path. --}}
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ $platform->appName() }}">
    </head>
    <body class="font-sans antialiased">
        <a href="#main-content" class="skip-to-content">{{ app()->getLocale() === 'ar' ? 'تخطَّ إلى المحتوى' : 'Skip to content' }}</a>
        <x-inertia::app />
    </body>
</html>
