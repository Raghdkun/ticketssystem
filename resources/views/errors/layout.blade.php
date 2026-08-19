@php
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $settings = app(\App\Services\Settings::class);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ $settings->appName($locale) }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    {{-- Deliberately not Vite-built: an error page must render even when the
         asset manifest is missing or a deploy is half-finished. --}}
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100dvh; display: grid; place-items: center;
            padding: 2rem; background: #0a0a0a; color: #fafafa;
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            line-height: {{ $rtl ? '1.75' : '1.6' }};
        }
        main { max-width: 32rem; text-align: center; }
        .mark { width: 3.5rem; height: 3.5rem; margin: 0 auto 1.5rem; }
        .code {
            font-size: .8125rem; letter-spacing: .12em; text-transform: uppercase;
            color: #818cf8; font-weight: 600;
        }
        h1 {
            margin: .5rem 0 .75rem; font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: 700; line-height: 1.25; text-wrap: balance;
        }
        p { margin: 0 0 2rem; color: #a1a1aa; text-wrap: pretty; }
        a.button {
            display: inline-block; padding: .75rem 1.5rem; min-height: 44px;
            border-radius: .75rem; background: #4f46e5; color: #fff;
            text-decoration: none; font-weight: 500;
            transition: background-color .2s ease;
        }
        a.button:hover { background: #4338ca; }
        a.button:focus-visible { outline: 2px solid #818cf8; outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <svg class="mark" viewBox="0 0 40 40" fill="none" aria-hidden="true">
            <rect width="40" height="40" rx="9" fill="#4f46e5"/>
            <path d="M8 13.5a2.5 2.5 0 0 1 2.5-2.5h19a2.5 2.5 0 0 1 2.5 2.5v2.6a.8.8 0 0 1-.62.78 4.2 4.2 0 0 0 0 8.24.8.8 0 0 1 .62.78v2.6a2.5 2.5 0 0 1-2.5 2.5h-19A2.5 2.5 0 0 1 8 28.5v-2.6a.8.8 0 0 1 .62-.78 4.2 4.2 0 0 0 0-8.24.8.8 0 0 1-.62-.78v-2.6Z"
                  stroke="#fff" stroke-width="2.4" stroke-linejoin="round"/>
            <path d="M20 15v2.6M20 20.4v2.6M20 25.8v2.6" stroke="#fff" stroke-width="2.4" stroke-linecap="round"/>
        </svg>

        <p class="code">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>

        {{-- Every dead end needs a way back. --}}
        <a class="button" href="{{ url('/') }}">@yield('cta')</a>
    </main>
</body>
</html>
