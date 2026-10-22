<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('phone')->nullable();
            $t->boolean('is_admin')->default(false);
            $t->boolean('suspended')->default(false);
        });
        Schema::create('zones', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('service_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description');
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('beneficiaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by')->constrained('users');
            $t->string('name');
            $t->string('phone');
            $t->text('address');
            $t->foreignId('zone_id')->constrained();
            $t->timestamps();
        });
        Schema::create('family_authorizations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('beneficiary_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('relationship');
            $t->string('status')->default('pending');
            $t->text('consent_note');
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->unique(['beneficiary_id', 'user_id']);
        });
        Schema::create('volunteer_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained();
            $t->string('status')->default('pending');
            $t->json('zone_ids');
            $t->json('category_ids');
            $t->json('availability');
            $t->unsignedTinyInteger('max_tasks')->default(1);
            $t->text('verification_note')->nullable();
            $t->foreignId('verified_by')->nullable()->constrained('users');
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
        });
        Schema::create('help_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('requester_id')->constrained('users');
            $t->foreignId('beneficiary_id')->constrained();
            $t->foreignId('service_category_id')->constrained();
            $t->foreignId('zone_id')->constrained();
            $t->string('title');
            $t->text('notes')->nullable();
            $t->text('pickup_address');
            $t->text('delivery_address');
            $t->string('contact_phone');
            $t->dateTime('scheduled_start');
            $t->dateTime('scheduled_end');
            $t->string('status')->default('matching')->index();
            $t->foreignId('volunteer_id')->nullable()->constrained('users');
            $t->unsignedInteger('assignment_version')->default(0);
            $t->unsignedInteger('match_round')->default(1);
            $t->text('resolution_reason')->nullable();
            $t->string('completion_kind')->nullable();
            $t->string('handover_hash')->nullable();
            $t->dateTime('handover_expires_at')->nullable();
            $t->unsignedTinyInteger('handover_attempts')->default(0);
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['volunteer_id', 'status']);
        });
        Schema::create('match_offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('help_request_id')->constrained();
            $t->foreignId('volunteer_id')->constrained('users');
            $t->unsignedInteger('match_round');
            $t->string('status')->default('pending');
            $t->dateTime('expires_at')->index();
            $t->timestamp('responded_at')->nullable();
            $t->timestamps();
            $t->unique(['help_request_id', 'volunteer_id', 'match_round']);
        });
        Schema::create('request_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('help_request_id')->constrained();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action');
            $t->json('details')->nullable();
            $t->timestamps();
        });
        Schema::create('incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('help_request_id')->constrained();
            $t->foreignId('reporter_id')->constrained('users');
            $t->text('description');
            $t->string('status')->default('open');
            $t->text('resolution')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('inbox_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('help_request_id')->nullable()->constrained();
            $t->string('title');
            $t->text('body');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->string('action');
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->json('details')->nullable();
            $t->timestamps();
        });
        Schema::create('settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->string('value');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'audit_logs', 'inbox_messages', 'incidents', 'request_events', 'match_offers', 'help_requests', 'volunteer_profiles', 'family_authorizations', 'beneficiaries', 'service_categories', 'zones'] as $n) {
            Schema::dropIfExists($n);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['phone', 'is_admin', 'suspended']));
    }
};
