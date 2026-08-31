<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailbox_accounts', function (Blueprint $table): void {
            $table->string('domain_verification_status', 20)->default('pending')->after('reply_to_address');
            $table->timestampTz('domain_verified_at')->nullable()->after('domain_verification_status');
            $table->foreignId('domain_verified_by')->nullable()->after('domain_verified_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('lead_replies', function (Blueprint $table): void {
            $table->unsignedTinyInteger('automation_attempts')->default(0)->after('automation_blockers');
            $table->timestampTz('automation_next_attempt_at')->nullable()->after('automation_attempts');
            $table->timestampTz('automation_failed_at')->nullable()->after('automation_next_attempt_at');
            $table->index(['organization_id', 'status', 'automation_next_attempt_at'], 'lead_replies_automation_retry_index');
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->timestampTz('initial_automation_next_attempt_at')->nullable()->after('initial_automation_attempted_at');
            $table->timestampTz('initial_automation_failed_at')->nullable()->after('initial_automation_completed_at');
        });

        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('data_retention_days')->default(730)->after('completeness');
            $table->boolean('privacy_cleanup_enabled')->default(false)->after('data_retention_days');
        });

        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->timestamp('last_health_alerted_at')->nullable()->after('last_backup_verified_at');
            $table->string('last_health_alert_signature', 64)->nullable()->after('last_health_alerted_at');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->dropColumn(['last_health_alerted_at', 'last_health_alert_signature']);
        });
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->dropColumn(['data_retention_days', 'privacy_cleanup_enabled']);
        });
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropColumn(['initial_automation_next_attempt_at', 'initial_automation_failed_at']);
        });
        Schema::table('lead_replies', function (Blueprint $table): void {
            $table->dropIndex('lead_replies_automation_retry_index');
            $table->dropColumn(['automation_attempts', 'automation_next_attempt_at', 'automation_failed_at']);
        });
        Schema::table('mailbox_accounts', function (Blueprint $table): void {
            $table->dropForeign(['domain_verified_by']);
            $table->dropColumn(['domain_verification_status', 'domain_verified_at', 'domain_verified_by']);
        });
    }
};
