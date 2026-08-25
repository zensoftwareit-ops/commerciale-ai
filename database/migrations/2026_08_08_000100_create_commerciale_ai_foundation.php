<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('Europe/Rome');
            $table->string('locale')->default('it');
            $table->timestamps();
        });

        Schema::create('organization_user', function (Blueprint $table): void {
            $table->uuid('organization_id');
            $table->foreignId('user_id');
            $table->string('role', 30);
            $table->timestamps();
            $table->primary(['organization_id', 'user_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('pipeline_stages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('slug');
            $table->string('system_category', 30);
            $table->unsignedSmallInteger('position');
            $table->string('color', 20)->default('slate');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'slug']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('inbound_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->string('key')->unique();
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('leads', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('inbound_source_id')->nullable();
            $table->uuid('pipeline_stage_id');
            $table->foreignId('assigned_to')->nullable();
            $table->string('external_id')->nullable();
            $table->string('source_label')->default('manual');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('email_normalized')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_normalized')->nullable();
            $table->string('company')->nullable();
            $table->string('requested_service')->nullable();
            $table->json('request_data')->nullable();
            $table->json('consent_data')->nullable();
            $table->string('operational_status', 30)->default('needs_action');
            $table->string('temperature', 10)->default('cold');
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestampTz('next_action_at')->nullable();
            $table->timestampTz('last_activity_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'operational_status']);
            $table->index(['organization_id', 'email_normalized']);
            $table->index(['organization_id', 'phone_normalized']);
            $table->unique(['inbound_source_id', 'external_id']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('inbound_source_id')->references('id')->on('inbound_sources')->nullOnDelete();
            $table->foreign('pipeline_stage_id')->references('id')->on('pipeline_stages')->restrictOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('lead_id');
            $table->foreignId('actor_id')->nullable();
            $table->string('type', 50);
            $table->string('title');
            $table->json('data')->nullable();
            $table->timestampTz('occurred_at');
            $table->index(['organization_id', 'lead_id', 'occurred_at']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('lead_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('lead_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('email_normalized')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_normalized')->nullable();
            $table->string('company')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'email_normalized']);
            $table->index(['organization_id', 'phone_normalized']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
        });

        Schema::create('webhook_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('inbound_source_id');
            $table->string('idempotency_key');
            $table->string('payload_hash', 64);
            $table->string('status', 30);
            $table->uuid('lead_id')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['inbound_source_id', 'idempotency_key']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('inbound_source_id')->references('id')->on('inbound_sources')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_receipts');
        Schema::dropIfExists('lead_contacts');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('inbound_sources');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
