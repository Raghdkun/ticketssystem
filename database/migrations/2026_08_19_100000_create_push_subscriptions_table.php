<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();

            // Subscriptions belong to a ticket, not a user: holders are
            // anonymous and are only ever addressed by their token.
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('fcm_token', 512);
            $table->string('locale', 2)->default('ar');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'fcm_token']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
