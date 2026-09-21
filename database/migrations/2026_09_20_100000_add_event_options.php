<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three options an owner asked for, all on the event row.
 *
 * - A null capacity means "as many as turn up": no seat count, no sold out,
 *   no waiting list. Zero was never a valid capacity, so null is unambiguous.
 * - An unlisted event is published and bookable at its own URL, and nowhere
 *   else: not on the home page, the venue page, the sitemap or the sidebar.
 * - A free event may confirm bookings on the spot instead of holding them
 *   for payment that will never come.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedInteger('total_quantity')->nullable()->change();
            $table->boolean('is_unlisted')->default(false)->after('status');
            $table->boolean('auto_confirm')->default(false)->after('is_unlisted');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['is_unlisted', 'auto_confirm']);
        });
    }
};
