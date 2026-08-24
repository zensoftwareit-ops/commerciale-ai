<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->unique();
            $table->string('legal_name')->nullable();
            $table->string('commercial_name')->nullable();
            $table->string('industry')->nullable();
            $table->text('business_description')->nullable();
            $table->text('products_services')->nullable();
            $table->string('service_area')->nullable();
            $table->text('ideal_customer')->nullable();
            $table->text('pricing_rules')->nullable();
            $table->text('differentiators')->nullable();
            $table->json('qualification_questions')->nullable();
            $table->text('exclusion_criteria')->nullable();
            $table->string('tone_of_voice')->default('professionale e diretto');
            $table->text('email_signature')->nullable();
            $table->text('appointment_details')->nullable();
            $table->unsignedInteger('promised_response_minutes')->nullable();
            $table->string('authorized_sender')->nullable();
            $table->unsignedTinyInteger('completeness')->default(0);
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('knowledge_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreignId('updated_by')->nullable();
            $table->string('title');
            $table->string('type', 30);
            $table->longText('content');
            $table->json('structured_data')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
            $table->index(['organization_id', 'status', 'type']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('qualification_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id')->unique();
            $table->json('rules');
            $table->unsignedTinyInteger('ai_weight')->default(60);
            $table->unsignedTinyInteger('rule_weight')->default(40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('prompt_policies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('operation', 50);
            $table->string('version', 30);
            $table->text('instructions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'operation', 'version']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('ai_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('lead_id')->nullable();
            $table->string('operation', 50);
            $table->string('status', 20);
            $table->string('provider', 50)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('policy_version', 30)->nullable();
            $table->json('input_context')->nullable();
            $table->json('output')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('input_units')->default(0);
            $table->unsignedInteger('output_units')->default(0);
            $table->decimal('estimated_cost', 12, 6)->default(0);
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'operation', 'status']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->nullOnDelete();
        });

        Schema::create('ai_analyses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('lead_id');
            $table->uuid('ai_run_id');
            $table->unsignedInteger('version');
            $table->text('summary');
            $table->string('intent');
            $table->json('requested_services');
            $table->json('budget');
            $table->string('urgency', 20);
            $table->unsignedTinyInteger('ai_score');
            $table->unsignedTinyInteger('rule_score');
            $table->unsignedTinyInteger('final_score');
            $table->string('priority', 20);
            $table->json('missing_information');
            $table->json('risk_flags');
            $table->text('recommended_next_action');
            $table->json('qualification_questions');
            $table->decimal('confidence', 4, 3);
            $table->foreignId('corrected_by')->nullable();
            $table->timestampTz('corrected_at')->nullable();
            $table->timestamps();
            $table->unique(['lead_id', 'version']);
            $table->index(['organization_id', 'priority']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->cascadeOnDelete();
            $table->foreign('corrected_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('usage_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('ai_run_id')->unique();
            $table->string('operation', 50);
            $table->string('provider', 50);
            $table->string('model', 100);
            $table->unsignedInteger('input_units')->default(0);
            $table->unsignedInteger('output_units')->default(0);
            $table->decimal('estimated_cost', 12, 6)->default(0);
            $table->timestampTz('occurred_at');
            $table->index(['organization_id', 'occurred_at']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('ai_run_id')->references('id')->on('ai_runs')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('ai_analyses');
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('prompt_policies');
        Schema::dropIfExists('qualification_profiles');
        Schema::dropIfExists('knowledge_documents');
        Schema::dropIfExists('organization_settings');
    }
};
