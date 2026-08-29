<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * A malformed language file is a runtime 500 on every page in that locale, and
 * a missing key renders the raw key to the user. Both are cheap to catch here.
 */
class TranslationCatalogueTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private function files(string $locale): array
    {
        return glob(base_path("lang/{$locale}/*.php")) ?: [];
    }

    /**
     * Every literal `t('a.b')` in the front end must resolve.
     *
     * The locale-parity test cannot catch a key that is missing from *both*
     * catalogues -- that renders the raw dot-path on screen and still passes.
     * This walks the call sites instead of the catalogue.
     */
    public function test_every_literal_translation_call_site_resolves(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'))
        );

        $missing = [];

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'tsx') {
                continue;
            }

            preg_match_all(
                "/\bt\(\s*'([a-z0-9_]+\.[a-z0-9_.]+)'/i",
                (string) file_get_contents($file->getPathname()),
                $matches
            );

            foreach ($matches[1] as $key) {
                foreach (SetLocale::SUPPORTED as $locale) {
                    if (! Arr::has(require base_path("lang/{$locale}/ui.php"), $key)) {
                        $missing[] = "{$key} ({$locale}) in ".$file->getFilename();
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }

    /**
     * Breadcrumb and nav titles must be translation keys, not literals.
     *
     * They are declared in static page config, outside any component, so they
     * cannot call the hook and are resolved at render instead. A literal
     * string there renders untranslated in both locales and looks deliberate.
     */
    public function test_every_declared_title_is_a_translation_key(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'))
        );

        $bad = [];

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'tsx') {
                continue;
            }

            preg_match_all(
                "/\btitle:\s*'([^']+)'/",
                (string) file_get_contents($file->getPathname()),
                $matches
            );

            foreach ($matches[1] as $title) {
                if (! preg_match('/^[a-z0-9_]+\.[a-z0-9_.]+$/i', $title)) {
                    $bad[] = "\"{$title}\" in ".$file->getFilename();

                    continue;
                }

                foreach (SetLocale::SUPPORTED as $locale) {
                    if (! Arr::has(require base_path("lang/{$locale}/ui.php"), $title)) {
                        $bad[] = "{$title} ({$locale}) in ".$file->getFilename();
                    }
                }
            }
        }

        $this->assertSame([], array_values(array_unique($bad)));
    }

    /**
     * No page announces itself in one fixed language.
     *
     * `<Head title="...">` takes a plain string, so an English literal there
     * shows up in the browser tab and in a shared link's title regardless of
     * locale -- which is exactly what three settings pages were doing.
     */
    public function test_no_page_title_is_a_hardcoded_string(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'))
        );

        $bad = [];

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'tsx') {
                continue;
            }

            preg_match_all(
                '/<Head\s+title="([^"]+)"/',
                (string) file_get_contents($file->getPathname()),
                $matches
            );

            foreach ($matches[1] as $literal) {
                $bad[] = "\"{$literal}\" in ".$file->getFilename();
            }
        }

        $this->assertSame([], $bad);
    }

    /**
     * No English prose sits directly in the markup.
     *
     * Arabic is the default language, so a literal sentence in a component
     * shows in English to every Arabic reader. The catalogue tests catch keys
     * that do not resolve; this catches text that never reached a key at all.
     */
    public function test_no_english_sentence_is_hardcoded_in_the_markup(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'))
        );

        $found = [];

        foreach ($files as $file) {
            // The vendored shadcn primitives carry their own English in
            // places no user reads, and are replaced wholesale on upgrade.
            if (! $file->isFile()
                || $file->getExtension() !== 'tsx'
                || str_contains($file->getPathname(), '/components/ui/')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            // Two or more capitalised-then-lowercase words sitting as a text
            // node between tags: prose, not a class name or an identifier.
            preg_match_all('/>\s*([A-Z][a-z]+ [a-z]{2,}[^<>{}]{0,60})</', $source, $matches);

            foreach ($matches[1] as $text) {
                $found[] = trim($text).' — '.$file->getFilename();
            }
        }

        $this->assertSame([], array_values(array_unique($found)));
    }

    public function test_every_language_file_parses(): void
    {
        // Linted as a subprocess rather than required: a parse error in a
        // required file is fatal and takes the whole suite down with it, so
        // the one thing this test exists to catch would report as a crash
        // instead of a failure naming the file and line.
        foreach (SetLocale::SUPPORTED as $locale) {
            foreach ($this->files($locale) as $file) {
                $output = [];
                $status = 0;
                exec('php -l '.escapeshellarg($file).' 2>&1', $output, $status);

                $this->assertSame(
                    0,
                    $status,
                    implode("\n", $output)
                );
            }
        }
    }

    public function test_every_language_file_returns_an_array(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            foreach ($this->files($locale) as $file) {
                $this->assertIsArray(require $file, "{$file} did not return an array.");
            }
        }
    }

    public function test_both_locales_ship_the_same_ui_keys(): void
    {
        $keys = [];

        foreach (SetLocale::SUPPORTED as $locale) {
            app()->setLocale($locale);
            $keys[$locale] = array_keys(Arr::dot(trans('ui')));
            sort($keys[$locale]);
        }

        $this->assertSame(
            $keys['en'],
            $keys['ar'],
            'The Arabic and English UI catalogues have drifted apart.'
        );
    }

    public function test_no_ui_string_is_empty(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            app()->setLocale($locale);

            foreach (Arr::dot(trans('ui')) as $key => $value) {
                $this->assertNotSame('', trim((string) $value), "ui.{$key} is empty in {$locale}.");
            }
        }
    }

    /**
     * The client falls back to rendering the key itself, so an Arabic string
     * that is still the English source would go unnoticed.
     */
    public function test_arabic_ui_strings_are_actually_translated(): void
    {
        app()->setLocale('en');
        $en = Arr::dot(trans('ui'));
        app()->setLocale('ar');
        $ar = Arr::dot(trans('ui'));

        // Values legitimately identical across locales (the language switcher
        // shows the *other* language's name, and brand names do not translate).
        $allowed = ['common.language'];

        foreach ($ar as $key => $value) {
            if (in_array($key, $allowed, true)) {
                continue;
            }

            $this->assertNotSame(
                $en[$key] ?? null,
                $value,
                "ui.{$key} is identical in both locales — likely untranslated."
            );
        }
    }
}
