<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\Event;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Copies an event forward on a cadence.
 *
 * A weekly night is the same event with a different date, and retyping the
 * rules, perks, price and seat count every week is the most repetitive thing
 * an owner does here.
 *
 * Copies always land as drafts, whoever makes them. A repeat inherits dates
 * that were computed rather than chosen, and going live is exactly the moment
 * somebody should look at them.
 */
final class RepeatEvent
{
    /**
     * Supported cadences and the interval each one steps by.
     *
     * Months are added as months, not as thirty days: "the first Thursday"
     * drifts otherwise, and drift is what makes a repeat feature useless.
     *
     * @var array<string, array{0: string, 1: int}>
     */
    public const CADENCES = [
        'daily' => ['days', 1],
        'weekly' => ['weeks', 1],
        'fortnightly' => ['weeks', 2],
        'monthly' => ['months', 1],
    ];

    public const MAX_COPIES = 12;

    /**
     * @param  int  $count  How many copies to make, 1..MAX_COPIES.
     * @return Collection<int, Event>
     */
    public function handle(Event $event, string $cadence, int $count): Collection
    {
        $count = max(1, min($count, self::MAX_COPIES));
        [$unit, $step] = self::CADENCES[$cadence] ?? self::CADENCES['weekly'];

        $event->loadMissing(['rules', 'perks', 'place']);

        return DB::transaction(function () use ($event, $unit, $step, $count): Collection {
            $copies = new Collection;

            for ($i = 1; $i <= $count; $i++) {
                $shift = $step * $i;

                $copy = $event->replicate([
                    // Nothing about how the original sold carries over.
                    'slug', 'status', 'promo_video_id', 'created_at', 'updated_at',
                ]);

                $copy->status = EventStatus::Draft;
                $copy->promo_video_id = null;
                $copy->starts_at = $this->shift($event->starts_at, $unit, $shift);
                $copy->ends_at = $event->ends_at === null
                    ? null
                    : $this->shift($event->ends_at, $unit, $shift);
                $copy->appointments_close_at = $this->shift($event->appointments_close_at, $unit, $shift);
                $copy->slug = $this->uniqueSlug($event, $copy->starts_at);
                $copy->save();

                // The cover is deliberately shared rather than re-processed:
                // a weekly night keeps its poster, and nothing ever deletes a
                // cover file, so pointing at the same one is safe.
                foreach ($event->rules as $rule) {
                    $copy->rules()->create($rule->only(['body_ar', 'body_en', 'sort']));
                }

                foreach ($event->perks as $perk) {
                    $copy->perks()->create($perk->only(['body_ar', 'body_en', 'sort']));
                }

                $copies->push($copy);
            }

            return $copies;
        });
    }

    private function shift(CarbonImmutable $moment, string $unit, int $amount): CarbonImmutable
    {
        return $moment->add($unit, $amount);
    }

    /**
     * Slugs are unique per venue, and every copy of a weekly night wants the
     * same one, so the date is what separates them.
     */
    private function uniqueSlug(Event $event, CarbonImmutable $startsAt): string
    {
        $base = Str::slug($event->title_en) ?: 'event';
        $base = $base.'-'.$startsAt->format('Y-m-d');
        $slug = $base;
        $i = 2;

        while ($event->place->events()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
