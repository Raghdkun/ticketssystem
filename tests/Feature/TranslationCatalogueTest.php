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

    public function test_every_language_file_parses(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            foreach ($this->files($locale) as $file) {
                $result = require $file;

                $this->assertIsArray($result, "{$file} did not return an array.");
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
