<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);            // image | video
            $table->string('path');
            $table->string('poster_path')->nullable();  // videos only
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'type', 'sort']);
        });

        Schema::table('events', function (Blueprint $table) {
            // A promo video shown in place of the static cover on the public
            // page. Nullable because most events will only ever have a cover.
            $table->foreignId('promo_video_id')->nullable()->after('cover_variants')
                ->constrained('event_media')->nullOnDelete();
        });

        Schema::create('event_perks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('body_ar');
            $table->string('body_en');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_video_id');
        });

        Schema::dropIfExists('event_perks');
        Schema::dropIfExists('event_media');
    }
};
