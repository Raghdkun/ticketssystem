<?php

use App\Enums\TicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Unguessable bearer token; this is the secret that grants access to
            // the public ticket page and names the broadcast channel.
            $table->string('public_token', 32)->unique();

            $table->string('full_name');
            $table->string('phone', 20);
            $table->unsignedSmallInteger('quantity');
            $table->string('status')->default(TicketStatus::Pending->value);

            $table->timestamp('hold_expires_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            // Snapshot of the rules actually agreed to, so later edits to an
            // event's rules cannot retroactively change what the user accepted.
            $table->timestamp('accepted_rules_at');
            $table->json('accepted_rule_ids');

            $table->string('locale', 2)->default('ar');
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['status', 'hold_expires_at']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
