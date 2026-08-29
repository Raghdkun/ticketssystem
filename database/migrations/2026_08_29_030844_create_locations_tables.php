<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Locations become their own thing.
 *
 * A venue is not always one address: an owner may run a main hall, a rooftop
 * and a garden, and an event happens at one of them. Location therefore moves
 * off `places` and onto its own table, and the columns it used to live in are
 * dropped so there is exactly one source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();

            $table->string('name_ar');
            $table->string('name_en');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address_ar')->nullable();
            $table->string('address_en')->nullable();
            $table->string('landmark_ar')->nullable();
            $table->string('landmark_en')->nullable();

            // The one an event falls back to when none was chosen, and the one
            // the venue itself shows. Exactly one per place.
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['place_id', 'sort']);
        });

        Schema::create('location_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['location_id', 'sort']);
        });

        Schema::table('events', function (Blueprint $table) {
            // Nullable: an event drafted before its venue was pinned, or one
            // whose location was deleted, still has to render.
            $table->foreignId('location_id')->nullable()->after('place_id')
                ->constrained()->nullOnDelete();
        });

        // Carry every venue's existing pin across before the columns go.
        foreach (DB::table('places')->get() as $place) {
            DB::table('locations')->insert([
                'place_id' => $place->id,
                'name_ar' => $place->name_ar,
                'name_en' => $place->name_en,
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                'address_ar' => $place->address_ar,
                'address_en' => $place->address_en,
                'landmark_ar' => $place->landmark_ar,
                'landmark_en' => $place->landmark_en,
                'is_primary' => true,
                'sort' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn([
                'latitude', 'longitude',
                'address_ar', 'address_en',
                'landmark_ar', 'landmark_en',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address_ar')->nullable();
            $table->string('address_en')->nullable();
            $table->string('landmark_ar')->nullable();
            $table->string('landmark_en')->nullable();
        });

        // Put the primary location back where it came from, so a rollback
        // does not silently lose every venue's pin.
        foreach (DB::table('locations')->where('is_primary', true)->get() as $location) {
            DB::table('places')->where('id', $location->place_id)->update([
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'address_ar' => $location->address_ar,
                'address_en' => $location->address_en,
                'landmark_ar' => $location->landmark_ar,
                'landmark_en' => $location->landmark_en,
            ]);
        }

        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::dropIfExists('location_images');
        Schema::dropIfExists('locations');
    }
};
