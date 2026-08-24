<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->boolean('conversation_automation_enabled')->default(false);
            $table->boolean('auto_send_quotes_enabled')->default(false);
            $table->boolean('internal_test_only')->default(true);
            $table->json('automation_allowed_recipients')->nullable();
            $table->unsignedTinyInteger('max_automatic_replies')->default(3);
            $table->decimal('max_auto_quote_amount', 12, 2)->nullable();
        });

        Schema::create('pricing_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->string('name');
            $table->json('keywords');
            $table->json('required_fields')->nullable();
            $table->decimal('minimum_price', 12, 2);
            $table->decimal('maximum_price', 12, 2);
            $table->text('includes')->nullable();
            $table->text('excludes')->nullable();
            $table->unsignedSmallInteger('validity_days')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organization_id', 'is_active']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('quotations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('lead_id');
            $table->uuid('pricing_rule_id');
            $table->uuid('lead_reply_id')->nullable();
            $table->unsignedInteger('version');
            $table->decimal('minimum_price', 12, 2);
            $table->decimal('maximum_price', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->json('input_snapshot');
            $table->json('missing_fields')->nullable();
            $table->boolean('auto_send_eligible')->default(false);
            $table->json('automation_blockers')->nullable();
            $table->timestamps();
            $table->unique(['lead_id', 'version']);
            $table->index(['organization_id', 'auto_send_eligible']);
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('lead_id')->references('id')->on('leads')->cascadeOnDelete();
            $table->foreign('pricing_rule_id')->references('id')->on('pricing_rules')->restrictOnDelete();
            $table->foreign('lead_reply_id')->references('id')->on('lead_replies')->nullOnDelete();
        });

        Schema::table('lead_replies', function (Blueprint $table): void {
            $table->string('reply_kind', 30)->default('general')->after('status');
            $table->string('delivery_mode', 20)->default('manual')->after('reply_kind');
            $table->boolean('automation_eligible')->default(false)->after('delivery_mode');
            $table->json('automation_blockers')->nullable()->after('automation_eligible');
        });
    }

    public function down(): void
    {
        Schema::table('lead_replies', fn (Blueprint $table) => $table->dropColumn(['reply_kind', 'delivery_mode', 'automation_eligible', 'automation_blockers']));
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('pricing_rules');
        Schema::table('organization_settings', fn (Blueprint $table) => $table->dropColumn([
            'conversation_automation_enabled', 'auto_send_quotes_enabled', 'internal_test_only',
            'automation_allowed_recipients', 'max_automatic_replies', 'max_auto_quote_amount',
        ]));
    }
};
