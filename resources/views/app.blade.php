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

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
        @php($platform = app(\App\Services\Settings::class))
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
        <meta property="og:image" content="{{ $og['image'] ?? url('/icons/icon-512.png') }}">
        @if (! empty($og['image']) && ! empty($og['width']))
            <meta property="og:image:width" content="{{ $og['width'] }}">
            <meta property="og:image:height" content="{{ $og['height'] }}">
        @endif
        <meta property="og:locale" content="{{ app()->getLocale() === 'ar' ? 'ar_SY' : 'en_GB' }}">
        <meta name="twitter:card" content="summary_large_image">

        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#E8E2D6" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#12110E" media="(prefers-color-scheme: dark)">
        {{-- The standard name; Apple's prefixed one stays for older iOS,
             which is the platform that most needs the home-screen path. --}}
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Tickets">
    </head>
    <body class="font-sans antialiased">
        <a href="#main-content" class="skip-to-content">{{ app()->getLocale() === 'ar' ? 'تخطَّ إلى المحتوى' : 'Skip to content' }}</a>
        <x-inertia::app />
    </body>
</html>
