<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            // The default deliberately preserves existing installations and the pilot.
            $table->string('status', 30)->default('active')->after('locale')->index();
            $table->timestampTz('onboarding_completed_at')->nullable()->after('status');
            $table->timestampTz('suspended_at')->nullable()->after('onboarding_completed_at');
            $table->string('suspension_reason')->nullable()->after('suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'onboarding_completed_at', 'suspended_at', 'suspension_reason']);
        });
    }
};
