<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organisers, and venues that let them in.
 *
 * A place is now either a venue or an organiser. An organiser has no
 * locations of its own and holds its events at a venue that has opted in:
 * `shares_locations` is the venue owner's blanket permission for other
 * accounts to pick its locations. The event still belongs to the organiser
 * -- their terms, their door -- only the address is borrowed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->string('kind', 16)->default('venue')->after('slug');
            $table->boolean('shares_locations')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn(['kind', 'shares_locations']);
        });
    }
};
