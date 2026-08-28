<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->timestamp('last_automation_started_at')->nullable();
            $table->timestamp('last_automation_completed_at')->nullable();
            $table->string('last_automation_status', 30)->nullable();
            $table->json('last_automation_summary')->nullable();
            $table->text('last_automation_error')->nullable();
            $table->timestamp('last_backup_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'last_automation_started_at', 'last_automation_completed_at',
                'last_automation_status', 'last_automation_summary',
                'last_automation_error', 'last_backup_verified_at',
            ]);
        });
    }
};
