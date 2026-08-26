<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            // Nullable throughout: a venue that predates this feature, or one
            // whose owner has not got round to it, must keep working.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address_ar')->nullable();
            $table->string('address_en')->nullable();

            // Street addressing is unreliable in As-Suwayda; a landmark is
            // what people actually navigate by, so it is a first-class field
            // rather than something buried in the address line.
            $table->string('landmark_ar')->nullable();
            $table->string('landmark_en')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn([
                'latitude', 'longitude',
                'address_ar', 'address_en',
                'landmark_ar', 'landmark_en',
            ]);
        });
    }
};
