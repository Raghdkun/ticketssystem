<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-event palette extraction is retired.
     *
     * One fixed identity now carries the whole product, so a ticket from any
     * venue is recognisably the same platform. The extracted colours and the
     * auto/manual theme switch have no consumer left.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'theme_mode',
                'primary_color',
                'secondary_color',
                'on_primary_color',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('theme_mode')->default('auto');
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('on_primary_color', 7)->nullable();
        });
    }
};
