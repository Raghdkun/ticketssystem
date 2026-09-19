<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The ناس brand, as shipped.
 *
 * A rebrand is the one change that touches every surface at once and is
 * verified almost entirely by eye. These pin the parts an eye misses: the
 * mark's geometry being the same in every copy, the old tokens being gone
 * rather than merely unused, the fonts coming from this origin, and the
 * PWA still receiving the new icons.
 */
class BrandTest extends TestCase
{
    private function wordmarkPath(string $source): string
    {
        preg_match('/ d="([^"]+)"/', $source, $m);

        $this->assertNotEmpty($m[1] ?? '', 'no path data found');

        return $m[1];
    }

    public function test_the_wordmark_source_is_normalised_and_recolourable(): void
    {
        $svg = (string) file_get_contents(resource_path('brand/nas-wordmark.svg'));

        // The handoff's ElementTree export carried namespace-prefixed tags,
        // which render as a file but cannot be inlined into JSX.
        $this->assertStringNotContainsString('<ns0:', $svg);
        $this->assertStringContainsString('viewBox="0 0 288.928 182.8"', $svg);
        $this->assertStringContainsString('fill="currentColor"', $svg);
        $this->assertStringNotContainsString('#0d0e0f', strtolower($svg));
        $this->assertStringNotContainsString('font-family', $svg);
    }

    public function test_every_copy_of_the_wordmark_is_the_same_drawing(): void
    {
        $source = $this->wordmarkPath((string) file_get_contents(resource_path('brand/nas-wordmark.svg')));

        // The React component reads its geometry from a generated constant.
        $ts = (string) file_get_contents(resource_path('js/components/brand/wordmark-path.ts'));
        preg_match("/WORDMARK_PATH =\s*'([^']+)'/", $ts, $m);
        $this->assertSame($source, $m[1] ?? null, 'wordmark-path.ts drifted from the SVG');

        // The error pages inline it by hand, because they cannot use Vite.
        $blade = (string) file_get_contents(resource_path('views/errors/layout.blade.php'));
        $this->assertSame($source, $this->wordmarkPath($blade), 'errors/layout.blade.php drifted from the SVG');
    }

    public function test_the_disc_component_matches_its_source(): void
    {
        $svg = (string) file_get_contents(resource_path('brand/nas-disc.svg'));
        preg_match('/<path\s+d="([^"]+)"/s', $svg, $m);

        $ts = (string) file_get_contents(resource_path('js/components/brand/disc-path.ts'));
        preg_match("/DISC_PATH =\s*'([^']+)'/", $ts, $t);

        $this->assertSame($m[1], $t[1] ?? null, 'disc-path.ts drifted from the SVG');
        $this->assertStringContainsString('#F66002', $svg);
    }

    public function test_the_manifest_carries_the_new_name_and_colours(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);

        $this->assertStringContainsString('ناس', $manifest['name']);
        $this->assertStringContainsString('ناس', $manifest['short_name']);
        $this->assertSame('#F6F1EA', $manifest['theme_color']);
        $this->assertSame('#F6F1EA', $manifest['background_color']);
        $this->assertStringNotContainsString('Swaida', json_encode($manifest));
    }

    public function test_the_service_worker_cache_was_bumped_for_the_new_icons(): void
    {
        $worker = (string) file_get_contents(public_path('sw.js'));

        // /icons/ is cached forever under the version name; without a bump
        // every installed home screen keeps the old mark.
        preg_match("/const VERSION = 'v(\d+)'/", $worker, $m);

        $this->assertGreaterThanOrEqual(3, (int) ($m[1] ?? 0));
    }

    public function test_the_old_brand_tokens_are_gone_not_just_unused(): void
    {
        $offenders = [];
        $pattern = '/--brand-(jade|saffron|basalt|paper)|brand-cta|brand-surface|shadow-brand|mark-animated|\bgrain\b/';

        foreach ($this->sources(['resources/js', 'resources/css']) as $file) {
            if (preg_match($pattern, (string) file_get_contents($file))) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        $this->assertSame([], $offenders);
    }

    public function test_the_only_gradient_left_is_the_photo_scrim(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertStringNotContainsString('gradient', $css);

        $offenders = [];

        foreach ($this->sources(['resources/js']) as $file) {
            if (preg_match('/linear-gradient|bg-gradient-|bg-linear-/', (string) file_get_contents($file))) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        // A legibility scrim over a cover photo is not brand decoration.
        $this->assertSame(['resources/js/pages/public/event.tsx'], $offenders);
    }

    public function test_the_fonts_are_committed_and_served_from_this_origin(): void
    {
        foreach ([
            'cairo-arabic-wght-normal.woff2',
            'cairo-latin-wght-normal.woff2',
            'cairo-latin-ext-wght-normal.woff2',
            'noto-sans-arabic-arabic-wght-normal.woff2',
            'ibm-plex-mono-latin-400-normal.woff2',
            'ibm-plex-mono-latin-500-normal.woff2',
            'OFL-cairo.txt',
            'OFL-noto-sans-arabic.txt',
            'OFL-ibm-plex-mono.txt',
        ] as $file) {
            $this->assertFileExists(resource_path('fonts/'.$file));
        }

        $css = (string) file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString("font-family: 'Cairo'", $css);
        $this->assertStringContainsString('font-weight: 200 1000', $css);

        $this->assertStringNotContainsString('bunny', (string) file_get_contents(base_path('vite.config.ts')));

        $blade = (string) file_get_contents(resource_path('views/app.blade.php'));
        $this->assertStringNotContainsString('@fonts', $blade);
        $this->assertStringContainsString('rel="preload" as="font"', $blade);
        $this->assertStringContainsString('og-default.png', $blade);
        // The title is the admin-editable name, not the deployment label.
        $this->assertStringNotContainsString("config('app.name'", $blade);
    }

    public function test_a_built_bundle_makes_no_font_request_to_a_cdn(): void
    {
        $assets = public_path('build/assets');

        if (! is_dir($assets)) {
            $this->markTestSkipped('no production build present');
        }

        foreach (glob($assets.'/*.css') ?: [] as $css) {
            $this->assertStringNotContainsString('bunny.net', (string) file_get_contents($css));
        }

        $this->assertNotEmpty(glob($assets.'/cairo-arabic-wght-normal-*.woff2'), 'Cairo was not emitted into the build');
    }

    public function test_the_old_name_has_left_the_brand_surface(): void
    {
        $offenders = [];

        $files = array_merge(
            [base_path('app/Services/Settings.php'), public_path('manifest.webmanifest'), lang_path('ar/ui.php'), lang_path('en/ui.php')],
            $this->sources(['resources/js']),
        );

        foreach ($files as $file) {
            if (preg_match('/Swaida|Suwayda|السويداء/u', (string) file_get_contents($file))) {
                $offenders[] = str_replace(base_path().'/', '', $file);
            }
        }

        // lang/*/poster.php keeps its place copy on purpose: it is the opt-in
        // heritage mood, not the brand.
        $this->assertSame([], $offenders);
    }

    /**
     * @param  array<int, string>  $dirs
     * @return array<int, string>
     */
    private function sources(array $dirs): array
    {
        $files = [];

        foreach ($dirs as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir)));

            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['ts', 'tsx', 'css'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
