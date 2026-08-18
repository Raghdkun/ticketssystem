<?php

namespace Tests\Feature;

use App\Actions\AppointTicket;
use App\Enums\TicketStatus;
use App\Exceptions\AppointmentException;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Proves the seat guarantee under genuine parallelism.
 *
 * These tests fork real OS processes so each contender holds its own
 * PostgreSQL connection. An in-process loop would pass even without the row
 * lock, so it would not test anything.
 */
class OverbookingTest extends TestCase
{
    // Truncation rather than transactions: forked children need to see
    // committed rows on their own connections.
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required to test real concurrency.');
        }
    }

    /**
     * Run $workers forked processes, each attempting one appointment.
     *
     * @return array{successes: int, failures: int}
     */
    private function race(Event $event, int $workers, int $quantity = 1): array
    {
        $directory = sys_get_temp_dir().'/race-'.bin2hex(random_bytes(6));
        mkdir($directory);

        DB::disconnect();

        $pids = [];

        for ($i = 0; $i < $workers; $i++) {
            $pid = pcntl_fork();

            if ($pid === 0) {
                // Child: take a connection of our own, then contend.
                DB::purge();

                $outcome = 'fail';

                try {
                    app(AppointTicket::class)->handle(
                        event: $event->fresh(),
                        fullName: "Racer {$i}",
                        phone: '+96399100000'.$i,
                        quantity: $quantity,
                        acceptedRuleIds: [],
                    );
                    $outcome = 'ok';
                } catch (AppointmentException) {
                    $outcome = 'fail';
                }

                file_put_contents("{$directory}/{$i}", $outcome);

                // Die immediately so PHPUnit's shutdown handlers do not run
                // a second time in the child.
                posix_kill(posix_getpid(), SIGKILL);
            }

            $pids[] = $pid;
        }

        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }

        $outcomes = array_map(
            fn (string $file): string => (string) file_get_contents($file),
            glob("{$directory}/*") ?: []
        );

        array_map('unlink', glob("{$directory}/*") ?: []);
        rmdir($directory);

        return [
            'successes' => count(array_filter($outcomes, fn ($o) => $o === 'ok')),
            'failures' => count(array_filter($outcomes, fn ($o) => $o === 'fail')),
        ];
    }

    public function test_only_one_of_many_simultaneous_requests_wins_the_last_seat(): void
    {
        $event = Event::factory()->create(['total_quantity' => 1]);

        $result = $this->race($event, workers: 8);

        $this->assertSame(1, $result['successes'], 'Exactly one contender should get the only seat.');
        $this->assertSame(7, $result['failures']);
        $this->assertSame(1, Ticket::where('event_id', $event->id)->count());
        $this->assertSame(1, $event->fresh()->seatsTaken());
    }

    public function test_capacity_is_never_exceeded_when_demand_outstrips_supply(): void
    {
        $event = Event::factory()->create(['total_quantity' => 5]);

        $result = $this->race($event, workers: 12);

        $this->assertSame(5, $result['successes']);
        $this->assertSame(7, $result['failures']);
        $this->assertSame(5, $event->fresh()->seatsTaken());
        $this->assertLessThanOrEqual(
            $event->total_quantity,
            (int) Ticket::where('event_id', $event->id)->sum('quantity')
        );
    }

    public function test_multi_seat_requests_cannot_straddle_the_capacity_boundary(): void
    {
        // 10 seats, each contender wants 4: at most two can succeed.
        $event = Event::factory()->create(['total_quantity' => 10, 'max_per_appointment' => 10]);

        $result = $this->race($event, workers: 6, quantity: 4);

        $this->assertSame(2, $result['successes']);
        $this->assertSame(8, $event->fresh()->seatsTaken());
        $this->assertSame(
            TicketStatus::Pending,
            Ticket::where('event_id', $event->id)->first()->status
        );
    }
}
