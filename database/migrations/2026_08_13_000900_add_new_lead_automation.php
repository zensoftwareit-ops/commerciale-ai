<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_settings', function (Blueprint $table): void {
            $table->boolean('auto_analyze_new_leads')->default(false);
            $table->boolean('auto_send_initial_email')->default(false);
            $table->timestampTz('new_lead_automation_started_at')->nullable();
        });
        Schema::table('leads', function (Blueprint $table): void {
            $table->unsignedTinyInteger('initial_automation_attempts')->default(0);
            $table->timestampTz('initial_automation_attempted_at')->nullable();
            $table->timestampTz('initial_automation_completed_at')->nullable();
            $table->text('initial_automation_error')->nullable();
            $table->index(['organization_id', 'initial_automation_completed_at', 'created_at'], 'leads_initial_automation_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_initial_automation_index');
            $table->dropColumn(['initial_automation_attempts', 'initial_automation_attempted_at', 'initial_automation_completed_at', 'initial_automation_error']);
        });
        Schema::table('organization_settings', fn (Blueprint $table) => $table->dropColumn(['auto_analyze_new_leads', 'auto_send_initial_email', 'new_lead_automation_started_at']));
    }
};
