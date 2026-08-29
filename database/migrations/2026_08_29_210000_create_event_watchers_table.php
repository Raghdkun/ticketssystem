<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The waiting list for a sold-out event.
         *
         * Seats genuinely do come back here -- holds lapse and people cancel --
         * so a sold-out event is not an ending. There is no mailer, so the
         * phone number is the point: push reaches whoever opted in, and the
         * owner can reach everyone else on WhatsApp from the event report.
         */
        Schema::create('event_watchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone');
            $table->string('locale', 5)->default('ar');
            $table->string('fcm_token', 512)->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            // One place in the queue per number per event. Joining twice is a
            // double tap, not a second claim.
            $table->unique(['event_id', 'phone']);
            $table->index(['event_id', 'notified_at']);
        });

        Schema::table('tickets', function (Blueprint $table) {
            // Stamped when the "your hold is about to lapse" push goes out, so
            // a holder is nudged once and not once per scheduler tick.
            $table->timestamp('reminder_sent_at')->nullable()->after('hold_expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_watchers');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
