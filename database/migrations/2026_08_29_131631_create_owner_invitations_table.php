<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The only way an account comes into existence.
 *
 * Registration stays closed: there is no open sign-up form. An administrator
 * invites a specific person, and that invitation is the single, expiring,
 * one-use permission to create exactly one account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_invitations', function (Blueprint $table) {
            $table->id();

            // Only the hash is stored, the way password resets work. A leaked
            // database must not hand somebody a working account-creation link.
            $table->string('token_hash', 64)->unique();

            // The account is created with this address and the invitee cannot
            // change it, so a forwarded link cannot be redeemed by a stranger.
            $table->string('email');

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('requires_approval')->default(false);

            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['email', 'accepted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_invitations');
    }
};
