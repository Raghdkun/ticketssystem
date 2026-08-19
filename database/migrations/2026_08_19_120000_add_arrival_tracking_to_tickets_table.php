<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            // How many of the booked seats actually turned up. Kept separate
            // from quantity so a party of five can check in three without
            // losing what they reserved or what they owe.
            $table->unsignedSmallInteger('arrived_quantity')->default(0)->after('quantity');
            $table->timestamp('no_show_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['arrived_quantity', 'no_show_at']);
        });
    }
};
