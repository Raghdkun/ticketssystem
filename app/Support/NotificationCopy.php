<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;

/**
 * Picks the wording for a notification.
 *
 * Every message kind is a list of variants rather than one fixed sentence.
 * The same person books repeatedly at the same venue, and a notification that
 * arrives word-for-word identical every time reads as machinery. The subject
 * never moves -- a paid ticket always says the ticket is paid -- only the
 * phrasing does.
 *
 * The variant is chosen at random, minus whichever one this stream used last,
 * so two consecutive messages to the same person are never the same sentence.
 * The exclusion is cached rather than stored: forgetting it costs nothing, and
 * it must not become a column that has to be migrated for every new kind.
 */
final class NotificationCopy
{
    /**
     * How long the last-used variant is remembered per stream.
     */
    private const MEMORY_DAYS = 30;

    /**
     * @param  string  $kind  Dot path under the `push` catalogue, e.g. `status.paid`.
     * @param  array<string, string|int>  $replace  `:placeholder` values.
     * @param  string  $stream  Identifies the recipient, so two people are not
     *                          kept in step with each other.
     */
    public static function pick(string $kind, string $locale, array $replace = [], string $stream = ''): string
    {
        $variants = self::variants($kind, $locale);

        if ($variants === []) {
            // A kind with no catalogue entry must not throw at send time; the
            // key itself is a legible failure in a notification tray.
            return 'push.'.$kind;
        }

        $index = self::choose($variants, $kind, $stream);

        return self::interpolate($variants[$index], $replace);
    }

    /**
     * The variants available for a kind, in one locale.
     *
     * @return array<int, string>
     */
    public static function variants(string $kind, string $locale): array
    {
        /** @var array<mixed>|string $lines */
        $lines = Lang::get('push.'.$kind, [], $locale);

        if (is_string($lines)) {
            // A missing key comes back as the key itself; a kind written as
            // one string is tolerated as a single-variant list.
            return $lines === 'push.'.$kind ? [] : [$lines];
        }

        return array_values(array_filter($lines, 'is_string'));
    }

    /**
     * @param  array<int, string>  $variants
     */
    private static function choose(array $variants, string $kind, string $stream): int
    {
        if (count($variants) === 1) {
            return 0;
        }

        $key = 'push-variant:'.$kind.':'.($stream !== '' ? $stream : 'shared');
        $last = Cache::get($key);

        $pool = array_values(array_filter(
            array_keys($variants),
            fn (int $index): bool => $index !== $last,
        ));

        $index = $pool[random_int(0, count($pool) - 1)];

        Cache::put($key, $index, now()->addDays(self::MEMORY_DAYS));

        return $index;
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private static function interpolate(string $line, array $replace): string
    {
        if ($replace === []) {
            return $line;
        }

        // Longest placeholder first, so :event_title is not eaten by :event.
        uksort($replace, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($replace as $placeholder => $value) {
            $line = str_replace(':'.$placeholder, (string) $value, $line);
        }

        return $line;
    }
}
