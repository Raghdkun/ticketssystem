<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->string('slug');
            $table->string('title_ar');
            $table->string('title_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();

            $table->string('cover_path')->nullable();
            $table->json('cover_variants')->nullable();

            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 3)->default('SYP');

            $table->unsignedInteger('total_quantity');
            $table->unsignedSmallInteger('max_per_appointment')->default(10);
            $table->unsignedSmallInteger('hold_hours')->default(24);

            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('appointments_close_at');

            $table->string('status')->default('draft');
            $table->string('theme_mode')->default('auto');
            $table->string('primary_color', 7)->nullable();
            $table->string('secondary_color', 7)->nullable();
            $table->string('on_primary_color', 7)->nullable();

            $table->timestamps();

            $table->unique(['place_id', 'slug']);
            $table->index(['status', 'appointments_close_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
