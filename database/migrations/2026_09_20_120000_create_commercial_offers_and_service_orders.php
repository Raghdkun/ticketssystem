<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The commercial layer under the Partner Terms.
 *
 * An offer is one venue's commercial arrangement -- fee, who pays it, when
 * it settles, what is included -- written by an administrator and accepted
 * by the venue. An order is one paid service on top of that. Both carry
 * their own acceptance record, on the row: they are per venue and accepted
 * once, so the row is the evidence and is frozen the moment it is signed.
 *
 * An event snapshot copies the accepted offer's figures onto the event at
 * the moment the owner publishes it, so a later offer cannot rewrite the
 * terms an event was sold under.
 *
 * Nothing here moves money. The platform has no payment gateway; these are
 * the terms that will be settled by hand, recorded so both sides can see
 * what was agreed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commercial_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            $table->string('title_ar');
            $table->string('title_en');

            // percentage | fixed | subscription | custom
            $table->string('fee_type', 16);
            $table->decimal('fee_value', 12, 2)->nullable();
            // customer | organizer | split | custom
            $table->string('fee_payer', 16);
            $table->unsignedSmallInteger('settlement_days')->nullable();
            $table->decimal('subscription_amount', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('included_services_ar')->nullable();
            $table->text('included_services_en')->nullable();
            $table->text('additional_terms_ar')->nullable();
            $table->text('additional_terms_en')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();

            $table->string('content_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();

            // The acceptance, on the row.
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->string('representative_name', 120)->nullable();
            $table->string('representative_role', 32)->nullable();
            $table->string('representative_phone', 32)->nullable();
            $table->string('acceptance_method', 32)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('commercial_offers')->nullOnDelete();
            $table->timestamps();

            $table->index(['place_id', 'status']);
        });

        Schema::create('event_commercial_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('commercial_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fee_type', 16);
            $table->decimal('fee_value', 12, 2)->nullable();
            $table->string('fee_payer', 16);
            $table->unsignedSmallInteger('settlement_days')->nullable();
            $table->string('currency', 3)->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained()->restrictOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('draft');
            // door_staff | registration_staff | event_management | promotion
            // | photography | video | design | equipment | logistics | other
            $table->string('service_type', 32);
            $table->string('title_ar');
            $table->string('title_en');
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('terms_ar')->nullable();
            $table->text('terms_en')->nullable();

            $table->string('content_hash', 64)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();

            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->string('representative_name', 120)->nullable();
            $table->string('representative_role', 32)->nullable();
            $table->string('representative_phone', 32)->nullable();
            $table->string('acceptance_method', 32)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['place_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
        Schema::dropIfExists('event_commercial_snapshots');
        Schema::dropIfExists('commercial_offers');
    }
};
