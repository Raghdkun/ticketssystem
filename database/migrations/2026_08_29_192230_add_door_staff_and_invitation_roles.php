<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Door staff: an account that belongs to one venue and can work its door.
 *
 * Deliberately not a second kind of owner. Somebody handed this account can
 * check people in and look up a ticket; they cannot create events, see what
 * the venue took, or invite anybody else. It is the role a venue actually
 * needs on the night, and the safest thing to give a casual helper.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Null for everyone else. An owner is known by owning places; a
            // door hand is known by this.
            $table->foreignId('door_staff_for')->nullable()->after('requires_approval')
                ->constrained('places')->nullOnDelete();
        });

        Schema::table('owner_invitations', function (Blueprint $table) {
            // Which venue, when the invitation is for door staff. Null means
            // an owner invitation, which brings its own venue with it.
            $table->foreignId('place_id')->nullable()->after('email')
                ->constrained()->cascadeOnDelete();

            $table->string('role', 20)->default('owner')->after('place_id');
        });
    }

    public function down(): void
    {
        Schema::table('owner_invitations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('place_id');
            $table->dropColumn('role');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('door_staff_for');
        });
    }
};
