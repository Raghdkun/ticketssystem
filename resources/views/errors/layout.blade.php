@php
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $settings = app(\App\Services\Settings::class);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ $settings->appName($locale) }}</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    {{-- Deliberately not Vite-built: an error page must render even when the
         asset manifest is missing or a deploy is half-finished. --}}
    <style>
        /* Basalt & Saffron, as literals: this stylesheet cannot reach the
           app's token layer, which is the whole point of it being inline. */
        :root {
            color-scheme: light dark;
            --bg: #e8e2d6; --fg: #191712; --muted: #6e675a;
            --accent: #0a5c49; --accent-hover: #062e24; --on-accent: #faf7f2;
            --ring: #12876a;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #12110e; --fg: #faf7f2; --muted: #a49b8b;
                --accent: #4fcba5; --accent-hover: #12876a; --on-accent: #062e24;
                --ring: #4fcba5;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100dvh; display: grid; place-items: center;
            padding: 2rem; background: var(--bg); color: var(--fg);
            font-family: system-ui, -apple-system, 'Segoe UI', sans-serif;
            line-height: {{ $rtl ? '1.75' : '1.6' }};
        }
        main { max-width: 32rem; text-align: center; }
        .mark { width: 3.5rem; height: 3.5rem; margin: 0 auto 1.5rem; display: block; }
        .code {
            font-size: .8125rem; letter-spacing: .12em; text-transform: uppercase;
            color: var(--accent); font-weight: 600;
        }
        h1 {
            margin: .5rem 0 .75rem; font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: 700; line-height: 1.25; text-wrap: balance;
        }
        p { margin: 0 0 2rem; color: var(--muted); text-wrap: pretty; }
        a.button {
            display: inline-block; padding: .75rem 1.5rem; min-height: 44px;
            border-radius: 1rem; background: var(--accent); color: var(--on-accent);
            text-decoration: none; font-weight: 500;
            transition: background-color .2s ease;
        }
        @media (pointer: coarse) { a.button { min-height: 52px; padding: 1rem 1.75rem; } }
        a.button:hover { background: var(--accent-hover); }
        a.button:focus-visible { outline: 2px solid var(--ring); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        <svg class="mark" viewBox="0 0 48 48" fill="none" aria-hidden="true">
            <path d="M12 10h14.5a3.5 3.5 0 0 0 7 0H36a6 6 0 0 1 6 6v16a6 6 0 0 1-6 6h-2.5a3.5 3.5 0 0 0-7 0H12a6 6 0 0 1-6-6V16a6 6 0 0 1 6-6Z" fill="#0A5C49"/>
            <path d="M30 16v17" stroke="#FAF7F2" stroke-width="2.6" stroke-linecap="round" stroke-dasharray="0 5.4"/>
            <circle cx="18.5" cy="24" r="4.6" fill="#FAF7F2"/>
            <circle cx="36" cy="24" r="3.2" fill="#E8A72B"/>
        </svg>

        <p class="code">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>

        {{-- Every dead end needs a way back. --}}
        <a class="button" href="{{ url('/') }}">@yield('cta')</a>
    </main>
</body>
</html>
