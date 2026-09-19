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
        /* The ناس palette, as literals: this stylesheet cannot reach the
           app's token layer, which is the whole point of it being inline.
           Keep in step with resources/css/app.css by hand. */
        :root {
            color-scheme: light dark;
            --bg: #F6F1EA; --fg: #0D0E0F; --muted: #6F6A64;
            --accent: #F66002; --accent-hover: #DD5600; --on-accent: #0D0E0F;
            --accent-text: #B84600; --ring: #F66002;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0D0E0F; --fg: #F6F1EA; --muted: #A39D95;
                --accent: #F66002; --accent-hover: #FF7A2A; --on-accent: #0D0E0F;
                --accent-text: #F66002; --ring: #F66002;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100dvh; display: grid; place-items: center;
            padding: 2rem; background: var(--bg); color: var(--fg);
            /* No webfont: this page must render with nothing else loaded. */
            font-family: 'Cairo', 'Noto Sans Arabic', system-ui, -apple-system, 'Segoe UI', sans-serif;
            line-height: {{ $rtl ? '1.75' : '1.6' }};
        }
        main { max-width: 32rem; text-align: center; }
        .mark { width: 5.5rem; height: auto; margin: 0 auto 1.5rem; display: block; color: var(--fg); }
        .code {
            font-size: .8125rem; letter-spacing: .12em; text-transform: uppercase;
            color: var(--accent-text); font-weight: 800;
        }
        h1 {
            margin: .5rem 0 .75rem; font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: 800; line-height: 1.25; text-wrap: balance;
        }
        p { margin: 0 0 2rem; color: var(--muted); text-wrap: pretty; }
        a.button {
            display: inline-block; padding: .75rem 1.5rem; min-height: 44px;
            border-radius: 10px; background: var(--accent); color: var(--on-accent);
            text-decoration: none; font-weight: 800;
            transition: background-color .2s ease;
        }
        @media (pointer: coarse) { a.button { min-height: 52px; padding: 1rem 1.75rem; } }
        a.button:hover { background: var(--accent-hover); }
        a.button:focus-visible { outline: 2px solid var(--ring); outline-offset: 2px; }
    </style>
</head>
<body>
    <main>
        {{-- The wordmark, inlined: the path is byte-identical to
             resources/brand/nas-wordmark.svg, and a test says so. --}}
        <svg class="mark" viewBox="0 0 288.928 182.8" fill="currentColor" aria-hidden="true">
            <g transform="translate(-104.656 -33.744)">
                <path d="m 375.31197,56.416 c -1.056,-5.808 -9.328,-9.328 -18.304,-12.144 -5.28,5.456 -9.68,11.792 -12.496,18.656 7.216,3.168 13.376,7.392 18.656,12.144 4.928,-3.872 13.2,-13.376 12.144,-18.656 z m -3.52,19.888 h -1.76 l -18.656,18.48 13.2,23.232 h -24.992 l -3.52,4.048 v 40.656 l 3.52,5.28 h 32.736 l 11.264,-37.136 v -12.848 c 0,-9.856 -3.872,-30.8 -11.792,-41.712 z m -31.85614,41.712 h -13.728 l -1.584,-74.272 h -1.76 l -20.592,14.08 6.688,110.176 h 30.976 l 3.168,-3.52 V 121.536 Z M 277.98387,80 h -1.76 l -18.656,18.304 15.664,28.16 -27.984,-8.272 3.52,-16.72 -7.216,-1.76 c -5.808,14.784 -12.32,17.776 -27.28,18.304 -2.464,-8.272 -5.808,-15.488 -9.504,-22 h -1.76 l -20.768,21.296 18.832,37.312 c -13.024,3.872 -25.696,6.16 -36.608,6.16 -24.288,0 -32.384,-7.216 -32.384,-24.64 0,-5.632 0.88,-12.32 2.288,-20.24 l -6.688,-2.464 -12.32,40.656 c -0.704,4.4 -0.704,8.976 -0.704,12.848 0,25.696 19.712,39.6 45.584,39.6 14.784,0 31.68,-4.576 48.048,-14.08 l 6.336,-24.992 c 7.04,-1.056 12.32,-3.872 16.544,-8.096 l 43.472,14.608 13.2,-43.12 c 0.176,-2.464 0.352,-5.104 0.352,-7.92 0,-14.96 -2.992,-32.032 -10.208,-42.944 z"/>
            </g>
        </svg>

        <p class="code">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>

        {{-- Every dead end needs a way back. --}}
        <a class="button" href="{{ url('/') }}">@yield('cta')</a>
    </main>
</body>
</html>
