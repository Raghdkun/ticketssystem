<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Partner Terms, versioned and accepted.
 *
 * A version is written as a draft, published once, and never edited again;
 * publishing a newer one retires it. An acceptance is a record of one venue
 * agreeing to one version: who, as whom, when, from where, and a hash of the
 * exact text they were shown. Nothing here is ever backfilled -- an owner
 * who was on the platform before the terms existed has not accepted them,
 * and is asked to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_versions', function (Blueprint $table) {
            $table->id();
            // One kind today. The column is here so a second document (a
            // privacy addendum, say) does not need a second table.
            $table->string('kind', 32)->default('partner_terms');
            $table->string('version', 20);
            $table->string('title_ar');
            $table->string('title_en');
            $table->text('body_ar');
            $table->text('body_en');
            // What changed since the last version, shown on re-acceptance.
            $table->text('change_note_ar')->nullable();
            $table->text('change_note_en')->nullable();
            $table->string('status', 16)->default('draft');
            // SHA-256 of the published text. An acceptance carries the same
            // hash, which is how it proves what was shown.
            $table->string('content_hash', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kind', 'version']);
            $table->index(['kind', 'status']);
        });

        Schema::create('agreement_acceptances', function (Blueprint $table) {
            $table->id();
            // Restrict, not cascade: an acceptance is a legal record and
            // must outlive any attempt to delete what it refers to.
            $table->foreignId('agreement_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('place_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Who accepted, as of that moment. Snapshots, not references:
            // the venue's details may change later and this must not.
            $table->string('legal_name', 160);
            $table->string('registration_number', 80)->nullable();
            $table->string('representative_name', 120);
            $table->string('representative_title', 80)->nullable();
            $table->string('representative_phone', 32);

            $table->string('content_hash', 64);
            $table->string('locale', 2);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            // How the phone was verified, if it was: none until a provider
            // is wired, then the provider's name.
            $table->string('otp_channel', 16)->default('none');
            $table->timestamp('otp_verified_at')->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->unique(['agreement_version_id', 'place_id']);
            $table->index(['place_id', 'accepted_at']);
        });

        Schema::table('places', function (Blueprint $table) {
            $table->string('legal_name', 160)->nullable()->after('name_en');
            $table->string('registration_number', 80)->nullable()->after('legal_name');
            $table->string('representative_name', 120)->nullable()->after('registration_number');
            $table->string('representative_title', 80)->nullable()->after('representative_name');
            $table->string('representative_phone', 32)->nullable()->after('representative_title');
        });

        // A first version to edit, as a draft. Nothing is enforced until an
        // administrator replaces this text with the real terms and publishes
        // it; no owner is asked to sign a placeholder.
        DB::table('agreement_versions')->insert([
            'kind' => 'partner_terms',
            'version' => '1.0',
            'title_ar' => 'شروط الشراكة',
            'title_en' => 'Partner Terms',
            'body_ar' => "نص مؤقت. استبدله بشروط الشراكة الفعلية قبل النشر.\n\nهذه الاتفاقية بين المنصة والجهة المشغّلة للمكان. تحدد ما تقدمه المنصة، وما يلتزم به الشريك، وكيف تُعالج الحجوزات والدفع في المكان.",
            'body_en' => "Placeholder text. Replace it with the real Partner Terms before publishing.\n\nThis agreement is between the platform and the organisation operating a venue. It sets out what the platform provides, what the partner undertakes, and how bookings and payment at the venue are handled.",
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropColumn([
                'legal_name', 'registration_number', 'representative_name',
                'representative_title', 'representative_phone',
            ]);
        });

        Schema::dropIfExists('agreement_acceptances');
        Schema::dropIfExists('agreement_versions');
    }
};
