<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_super_admin')->default(false)->after('password');
            $table->string('external_account_id')->nullable()->unique()->after('is_super_admin');
        });
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('billing_account_ref')->nullable()->unique()->after('slug');
        });
        Schema::create('license_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('annual_price_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedSmallInteger('seat_limit')->default(1);
            $table->unsignedInteger('monthly_lead_limit')->nullable();
            $table->unsignedBigInteger('monthly_ai_token_limit')->nullable();
            $table->json('features')->nullable();
            $table->string('stripe_price_id')->nullable()->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
        Schema::create('licenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('license_plan_id');
            $table->uuid('organization_id');
            $table->foreignId('owner_user_id');
            $table->string('key')->unique();
            $table->string('status', 30)->default('active');
            $table->string('source', 30)->default('manual');
            $table->string('external_account_id')->nullable()->index();
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('current_period_ends_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->foreign('license_plan_id')->references('id')->on('license_plans')->restrictOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('owner_user_id')->references('id')->on('users')->restrictOnDelete();
        });
        Schema::create('license_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('license_id')->nullable();
            $table->string('external_event_id')->unique();
            $table->string('source', 30);
            $table->string('type', 80);
            $table->string('payload_hash', 64);
            $table->string('status', 30)->default('processed');
            $table->json('payload')->nullable();
            $table->timestampTz('processed_at');
            $table->timestamps();
            $table->foreign('license_id')->references('id')->on('licenses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_events');
        Schema::dropIfExists('licenses');
        Schema::dropIfExists('license_plans');
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropUnique(['billing_account_ref']);
            $table->dropColumn('billing_account_ref');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['external_account_id']);
            $table->dropColumn(['is_super_admin', 'external_account_id']);
        });
    }
};

