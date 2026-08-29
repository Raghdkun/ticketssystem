<?php

namespace Tests\Feature;

use App\Support\NotificationCopy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every notification rotates between wordings. The subject must never move --
 * a paid ticket always says paid -- but the sentence should.
 */
class NotificationCopyTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function kinds(): array
    {
        return [
            'pending' => ['status.pending'],
            'paid' => ['status.paid'],
            'cancelled' => ['status.cancelled'],
            'expired' => ['status.expired'],
            'no-show' => ['status.no_show'],
            'reminder' => ['reminder'],
            'seat freed' => ['seat_freed'],
        ];
    }

    #[DataProvider('kinds')]
    public function test_every_kind_carries_several_wordings_in_both_locales(string $kind): void
    {
        foreach (['ar', 'en'] as $locale) {
            $variants = NotificationCopy::variants($kind, $locale);

            $this->assertGreaterThanOrEqual(
                4,
                count($variants),
                "{$kind} has too few wordings in {$locale} to rotate between."
            );

            $this->assertSame(
                $variants,
                array_unique($variants),
                "{$kind} repeats a wording in {$locale}, which wastes a slot."
            );
        }
    }

    #[DataProvider('kinds')]
    public function test_both_locales_offer_the_same_number_of_wordings(string $kind): void
    {
        $this->assertSameSize(
            NotificationCopy::variants($kind, 'en'),
            NotificationCopy::variants($kind, 'ar'),
            "{$kind} rotates through more wordings in one language than the other."
        );
    }

    public function test_it_never_sends_the_same_wording_twice_in_a_row(): void
    {
        $previous = null;

        // Twenty consecutive sends to one person: not one may repeat the
        // sentence that came immediately before it.
        for ($i = 0; $i < 20; $i++) {
            $body = NotificationCopy::pick('status.paid', 'en', [], 'ticket:1');

            $this->assertNotSame($previous, $body);

            $previous = $body;
        }
    }

    public function test_two_recipients_do_not_march_in_step(): void
    {
        // Separate streams keep separate memory, so one person's message
        // never constrains another's.
        $first = NotificationCopy::pick('status.paid', 'en', [], 'ticket:1');
        $second = NotificationCopy::pick('status.paid', 'en', [], 'ticket:2');

        $this->assertContains($first, NotificationCopy::variants('status.paid', 'en'));
        $this->assertContains($second, NotificationCopy::variants('status.paid', 'en'));
    }

    public function test_it_actually_rotates_rather_than_alternating(): void
    {
        $seen = [];

        for ($i = 0; $i < 60; $i++) {
            $seen[] = NotificationCopy::pick('status.paid', 'en', [], 'ticket:1');
        }

        $this->assertGreaterThanOrEqual(
            4,
            count(array_unique($seen)),
            'Sixty sends should have reached most of the wordings, not bounced between two.'
        );
    }

    public function test_placeholders_are_filled(): void
    {
        $body = NotificationCopy::pick('reminder', 'en', ['time' => '8 Sep, 20:00'], 'ticket:9');

        $this->assertStringContainsString('8 Sep, 20:00', $body);
        $this->assertStringNotContainsString(':time', $body);
    }

    public function test_the_seat_freed_notice_names_the_event(): void
    {
        foreach (['ar', 'en'] as $locale) {
            $body = NotificationCopy::pick('seat_freed', $locale, ['event' => 'Dabke Night'], 'watcher:3');

            $this->assertStringContainsString('Dabke Night', $body);
            $this->assertStringNotContainsString(':event', $body);
        }
    }

    public function test_an_unknown_kind_degrades_to_its_key_rather_than_throwing(): void
    {
        $this->assertSame('push.status.invented', NotificationCopy::pick('status.invented', 'en'));
    }
}
