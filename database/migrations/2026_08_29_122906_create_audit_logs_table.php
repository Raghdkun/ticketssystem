<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acts worth being able to answer "who did that, and when" about.
 *
 * Deliberately not folded into impersonation_logs: an impersonation is a
 * session with a start and an end, while these are instants. Conflating them
 * would make both harder to read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Nullable so a record survives the actor's account being deleted.
            // Losing the actor must not lose the fact that it happened.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action', 40)->index();
            $table->json('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
